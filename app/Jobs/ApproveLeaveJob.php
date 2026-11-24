<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveLedger;
use App\Models\LeaveRequest;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use App\Services\Leave\LeaveLedgerService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class ApproveLeaveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Leave types that never carry pay. */
    private const UNPAID_CODES = ['LWP', 'LWOP', 'VLWOP', 'SLWOP'];

    public $employee;

    public $leaveRequest;

    public function __construct(LeaveRequest $leaveRequest, $employee, public ?int $approverId = null)
    {
        $this->leaveRequest = $leaveRequest;
        $this->employee = $employee;
    }

    /**
     * Post the approved leave to attendance and the leave credit balances.
     *
     * Only scheduled working days are written; rest days and holidays are not
     * leave. Paid and unpaid days are marked explicitly so payroll can pay a
     * daily-paid employee for statutory paid leave and not pay unpaid leave.
     */
    public function handle(): void
    {
        // Queued jobs do not inherit the request's scoped tenant context.
        app(TenantContext::class)->run((int) $this->leaveRequest->company_id, function (): void {
            $this->approve();
        });
    }

    private function approve(): void
    {
        $this->leaveRequest->approved_user_id = $this->approverId ?? Auth::id();
        $this->leaveRequest->status = STATUS_APPROVED;
        $this->leaveRequest->approved_at = now();

        $employee = Employee::find($this->leaveRequest->employee_id);

        if ($employee === null) {
            $this->leaveRequest->save();

            return;
        }

        $leave = $this->leaveRequest->leave()->withoutGlobalScopes()->first();
        $code = (string) $leave?->code |> trim(...) |> strtoupper(...);

        $ledger = app(LeaveLedgerService::class);
        $policy = $ledger->policyFor((int) $this->leaveRequest->company_id, (int) $this->leaveRequest->leave_type_id);

        $isUnpaidType = $policy !== null
            ? ! $policy->is_paid
            : in_array($code, self::UNPAID_CODES, true);

        $fraction = $this->leaveRequest->is_whole_day ? 1.0 : 0.5;
        $paidRemaining = $this->leaveRequest->days_with_pay !== null
            ? (float) $this->leaveRequest->days_with_pay
            : ($isUnpaidType ? 0.0 : PHP_FLOAT_MAX);
        $unpaidRemaining = (float) ($this->leaveRequest->days_without_pay ?? 0);
        $paidDaysWritten = 0.0;

        $dates = CarbonPeriod::create($this->leaveRequest->date_from, $this->leaveRequest->date_to);

        foreach ($dates as $date) {
            $logDate = CarbonImmutable::parse($date)->toDateString();

            if (! $this->isWorkingDay($employee, $logDate)) {
                continue;
            }

            $timesheet = Timesheet::firstOrNew([
                'log_date' => $logDate,
                'employee_id' => $this->leaveRequest->employee_id,
                'employee_number' => $employee->employee_number,
            ]);

            // An approved payroll owns these days; do not mutate them.
            if ($timesheet->exists && (bool) $timesheet->is_locked) {
                continue;
            }

            $isPaid = ! $isUnpaidType && $paidRemaining >= $fraction;

            if ($isPaid) {
                $paidRemaining -= $fraction;
                $paidDaysWritten += $fraction;
            } else {
                $unpaidRemaining -= $fraction;
            }

            $timesheet->is_leave = true;
            $timesheet->is_absent = false;
            $timesheet->is_paid_leave = $isPaid;
            $timesheet->day_rate_code = $isPaid ? 'pl' : 'upl';
            $timesheet->leave_type_id = $this->leaveRequest->leave_type_id;
            $timesheet->leave_half_day = ! $this->leaveRequest->is_whole_day;
            $timesheet->save();
        }

        $this->leaveRequest->save();

        // Credit-bearing leave draws down the ledger. Event-based statutory
        // leaves (maternity, paternity, VAWC, ...) do not.
        if ($paidDaysWritten > 0 && $policy !== null && $policy->accrual !== 'none') {
            $ledger->post(
                (int) $this->leaveRequest->company_id,
                (int) $this->leaveRequest->employee_id,
                (int) $this->leaveRequest->leave_type_id,
                (int) CarbonImmutable::parse($this->leaveRequest->date_from)->year,
                LeaveLedger::MOVEMENT_AVAILMENT,
                -$paidDaysWritten,
                'leave_request:'.$this->leaveRequest->getKey(),
                'Approved leave',
                $this->leaveRequest->approved_user_id,
            );
        }

        // Legacy columns remain in step for screens that still read them.
        $credited = $paidDaysWritten;

        if ($credited > 0) {
            if ($code === 'VL') {
                $employee->total_vacation_leave = max(0, (float) $employee->total_vacation_leave - $credited);
            }

            if ($code === 'SL') {
                $employee->total_sick_leave = max(0, (float) $employee->total_sick_leave - $credited);
            }

            $employee->save();
        }
    }

    /**
     * A leave day is only a working day when it is not the employee's rest day
     * and not a declared holiday.
     */
    private function isWorkingDay(Employee $employee, string $date): bool
    {
        $day = CarbonImmutable::parse($date);

        $daysYear = $employee->payrollGroup?->days_year ?? PayrollGroup::NUMBER_WORKING_DAYS_MONFRI;

        if ($daysYear === PayrollGroup::NUMBER_WORKING_DAYS_MONFRI && $day->isWeekend()) {
            return false;
        }

        if ($daysYear === PayrollGroup::NUMBER_WORKING_DAYS_MONSAT && $day->isSunday()) {
            return false;
        }

        return ! Holiday::query()->whereDate('date', $date)->exists();
    }
}
