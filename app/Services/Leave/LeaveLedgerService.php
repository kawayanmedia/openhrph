<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveLedger;
use App\Models\LeavePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Posts leave movements and keeps the balance projection in step.
 *
 * Every reference is unique per employee/type/movement, so re-running an
 * accrual, approval or carry-over cannot double-post.
 */
final class LeaveLedgerService
{
    /** Leave types that are earned credits. Everything else is event-based. */
    private const CREDIT_CODES = ['VL', 'SL', 'SIL', 'FL', 'SPL'];

    private const UNPAID_CODES = ['LWP', 'LWOP', 'VLWOP', 'SLWOP'];

    public function policyFor(int $companyId, int $leaveTypeId): ?LeavePolicy
    {
        $policy = LeavePolicy::query()
            ->where('company_id', $companyId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if ($policy !== null) {
            return $policy;
        }

        $type = Leave::query()->withoutGlobalScopes()->find($leaveTypeId);

        if ($type === null) {
            return null;
        }

        $code = strtoupper(trim((string) $type->code));
        $credit = (bool) $type->deductable || in_array($code, self::CREDIT_CODES, true);

        $minService = match ($code) {
            'SIL' => 12,
            'SPL-SP', 'SLW' => 6,
            default => 0,
        };

        return LeavePolicy::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'leave_type_id' => $leaveTypeId,
            ],
            [
                'entitlement_days' => (float) ($type->allow_per_year ?? 0),
                'accrual' => $credit ? 'annual' : 'none',
                'prorate_first_year' => true,
                'carry_over_days' => 0,
                'expiry_months' => 0,
                'half_day_allowed' => true,
                'is_paid' => ! in_array($code, self::UNPAID_CODES, true),
                'min_service_months' => $minService,
                'requires_evidence' => in_array($code, ['ML', 'PL', 'VAWC', 'SLW', 'ADOPT'], true),
                'is_active' => true,
            ],
        );
    }

    /**
     * Post a movement. Positive days add to the balance; negative days reduce it.
     */
    public function post(
        int $companyId,
        int $employeeId,
        int $leaveTypeId,
        int $year,
        string $movement,
        float $days,
        ?string $reference = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): LeaveLedger {
        return DB::transaction(function () use ($companyId, $employeeId, $leaveTypeId, $year, $movement, $days, $reference, $reason, $actorId): LeaveLedger {
            if ($reference !== null) {
                $existing = LeaveLedger::query()
                    ->where('company_id', $companyId)
                    ->where('employee_id', $employeeId)
                    ->where('leave_type_id', $leaveTypeId)
                    ->where('movement', $movement)
                    ->where('reference', $reference)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $entry = LeaveLedger::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'period_year' => $year,
                'movement' => $movement,
                'days' => round($days, 2),
                'reference' => $reference,
                'reason' => $reason,
                'actor_id' => $actorId,
            ]);

            $balance = LeaveBalance::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'year' => $year,
                ],
                ['balance' => 0, 'accrued' => 0, 'used' => 0, 'carried' => 0, 'expired' => 0, 'adjusted' => 0],
            );

            $balance->balance = round((float) $balance->balance + $days, 2);

            switch ($movement) {
                case LeaveLedger::MOVEMENT_ACCRUAL:
                    $balance->accrued = round((float) $balance->accrued + $days, 2);
                    break;
                case LeaveLedger::MOVEMENT_CARRY_OVER:
                    $balance->carried = round((float) $balance->carried + $days, 2);
                    break;
                case LeaveLedger::MOVEMENT_EXPIRY:
                    $balance->expired = round((float) $balance->expired + abs($days), 2);
                    break;
                case LeaveLedger::MOVEMENT_AVAILMENT:
                case LeaveLedger::MOVEMENT_MONETIZATION:
                    $balance->used = round((float) $balance->used + abs($days), 2);
                    break;
                default:
                    $balance->adjusted = round((float) $balance->adjusted + $days, 2);
                    break;
            }

            $balance->save();

            return $entry;
        });
    }

    public function balance(int $companyId, int $employeeId, int $leaveTypeId, int $year): float
    {
        return (float) LeaveLedger::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('period_year', $year)
            ->sum('days');
    }

    /**
     * Accrue the annual entitlement for every active employee and credit policy.
     */
    public function accrueYear(int $companyId, int $year): int
    {
        $posted = 0;
        $employees = Employee::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->get();

        // Materialise a policy for every active type so a company can accrue
        // without opening each catalogue row first.
        Leave::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn ($typeId) => $this->policyFor($companyId, (int) $typeId));

        $policies = LeavePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('accrual', '!=', 'none')
            ->get();

        foreach ($employees as $employee) {
            foreach ($policies as $policy) {
                if (! $this->eligible($employee, $policy, $year)) {
                    continue;
                }

                $entitlement = (float) $policy->entitlement_days;

                if ($entitlement <= 0) {
                    continue;
                }

                $entitlement = $this->prorated($employee, $policy, $entitlement, $year);

                if ($entitlement <= 0) {
                    continue;
                }

                if ($policy->accrual === 'monthly') {
                    $months = now()->year === $year ? now()->month : 12;

                    for ($month = 1; $month <= $months; $month++) {
                        $this->post(
                            $companyId, (int) $employee->getKey(), (int) $policy->leave_type_id,
                            $year, LeaveLedger::MOVEMENT_ACCRUAL, round($entitlement / 12, 2),
                            "accrual:{$year}:{$month}", 'Monthly accrual',
                        );
                        $posted++;
                    }

                    continue;
                }

                $this->post(
                    $companyId, (int) $employee->getKey(), (int) $policy->leave_type_id,
                    $year, LeaveLedger::MOVEMENT_ACCRUAL, $entitlement,
                    "accrual:{$year}", 'Annual accrual',
                );
                $posted++;
            }
        }

        return $posted;
    }

    /**
     * Move the unused balance into the next year and expire the excess.
     */
    public function carryOver(int $companyId, int $fromYear, int $toYear): int
    {
        $posted = 0;

        $balances = LeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('year', $fromYear)
            ->where('balance', '>', 0)
            ->get();

        foreach ($balances as $balance) {
            $policy = $this->policyFor($companyId, (int) $balance->leave_type_id);

            if ($policy === null || $policy->accrual === 'none') {
                continue;
            }

            $cap = (float) $policy->carry_over_days;
            $remaining = (float) $balance->balance;
            $carried = $cap > 0 ? min($remaining, $cap) : 0.0;

            if ($carried > 0) {
                // Debit the closing year and credit the opening year so the
                // same days are not counted twice.
                $this->post(
                    $companyId, (int) $balance->employee_id, (int) $balance->leave_type_id,
                    $fromYear, LeaveLedger::MOVEMENT_CARRY_OVER, -$carried,
                    "carry-out:{$fromYear}:{$toYear}", 'Carry-over to next year',
                );
                $this->post(
                    $companyId, (int) $balance->employee_id, (int) $balance->leave_type_id,
                    $toYear, LeaveLedger::MOVEMENT_CARRY_OVER, $carried,
                    "carry:{$fromYear}:{$toYear}", 'Carry-over from previous year',
                );
                $posted += 2;
            }

            $expired = $remaining - $carried;

            if ($expired > 0) {
                $this->post(
                    $companyId, (int) $balance->employee_id, (int) $balance->leave_type_id,
                    $fromYear, LeaveLedger::MOVEMENT_EXPIRY, -$expired,
                    "expiry:{$fromYear}:{$toYear}", 'Expired unused leave',
                );
                $posted++;
            }
        }

        return $posted;
    }

    public function monetize(int $companyId, int $employeeId, int $leaveTypeId, int $year, float $days, ?int $actorId = null): LeaveLedger
    {
        $available = $this->balance($companyId, $employeeId, $leaveTypeId, $year);

        if ($days <= 0 || $days > $available) {
            throw new \InvalidArgumentException('Monetized days must be within the available balance.');
        }

        return $this->post(
            $companyId, $employeeId, $leaveTypeId, $year,
            LeaveLedger::MOVEMENT_MONETIZATION, -$days,
            'monetize:'.uniqid('', true), 'Leave monetization', $actorId,
        );
    }

    private function eligible(Employee $employee, LeavePolicy $policy, int $year): bool
    {
        if ($employee->date_hired === null) {
            return false;
        }

        $hired = CarbonImmutable::parse($employee->date_hired);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        if ($employee->date_resigned !== null && CarbonImmutable::parse($employee->date_resigned)->lt($yearEnd)) {
            $yearEnd = CarbonImmutable::parse($employee->date_resigned);
        }

        $months = $hired->diffInMonths($yearEnd, false);

        return $months >= (int) $policy->min_service_months;
    }

    private function prorated(Employee $employee, LeavePolicy $policy, float $entitlement, int $year): float
    {
        if (! $policy->prorate_first_year || $employee->date_hired === null) {
            return $entitlement;
        }

        $hired = CarbonImmutable::parse($employee->date_hired);

        if ($hired->year < $year) {
            return $entitlement;
        }

        $remainingMonths = 12 - $hired->month + 1;

        return round($entitlement * max(0, $remainingMonths) / 12, 2);
    }
}
