<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\Leave;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyRule;
use Illuminate\Support\Carbon;

/**
 * Resolves the effective private leave policy rule for an employee.
 *
 * Eligibility here is advisory data for the leave request workflow; the leave
 * domain still owns balance and schedule validation.
 */
class PrivateLeavePolicyService
{
    /**
     * @return array{policy: LeavePolicy|null, rule: LeavePolicyRule|null, eligible: bool, reasons: array<int, string>}
     */
    public function resolve(Employee $employee, Leave $leaveType, ?Carbon $asOf = null): array
    {
        $asOf ??= now();

        $policy = LeavePolicy::query()
            ->where('leave_type_id', $leaveType->getKey())
            ->where('is_active', true)
            ->first();

        if ($policy === null) {
            return [
                'policy' => null,
                'rule' => null,
                'eligible' => false,
                'reasons' => ['No active leave policy exists for this leave type.'],
            ];
        }

        $employment = Employment::withoutTenant()
            ->where('employee_id', $employee->getKey())
            ->where('status', Employment::STATUS_ACTIVE)
            ->latest('start_date')
            ->first();

        $classification = $employment?->classification;

        $rules = LeavePolicyRule::query()
            ->where('leave_policy_id', $policy->getKey())
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $asOf->toDateString()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $asOf->toDateString()))
            ->where(fn ($query) => $query->whereNull('employment_classification')
                ->orWhere('employment_classification', $classification))
            ->get()
            ->sortByDesc(fn (LeavePolicyRule $rule) => $rule->employment_classification === null ? 0 : 1)
            ->values();

        $rule = $rules->first();

        $reasons = [];
        $serviceMonths = $this->serviceMonths($employee, $employment, $asOf);

        $requiredMonths = $rule?->min_service_months ?? $policy->min_service_months;

        if ($requiredMonths !== null && $serviceMonths < $requiredMonths) {
            $reasons[] = "Requires at least {$requiredMonths} months of service ({$serviceMonths} recorded).";
        }

        return [
            'policy' => $policy,
            'rule' => $rule,
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    protected function serviceMonths(Employee $employee, ?Employment $employment, Carbon $asOf): int
    {
        $start = $employment?->start_date ?? $employee->date_hired;

        if ($start === null) {
            return 0;
        }

        return max(0, (int) $start->diffInMonths($asOf));
    }
}
