<?php

namespace App\Livewire\Portal\Leaves;

use App\Actions\CreateLeaveAction;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveLedger;
use App\Models\PayrollGroup;
use App\Services\Leave\LeaveLedgerService;
use App\Support\Tenancy\TenantCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class LeaveCreate extends Component
{
    use WithFileUploads;

    /** Leave types that never carry pay. */
    private const UNPAID_CODES = ['LWP', 'LWOP', 'VLWOP', 'SLWOP'];

    public $title = 'Create Leave';

    public $leaveTypeId;

    public $dateFrom;

    public $dateTo;

    public $daysWithPay;

    public $daysWithoutPay;

    public $reason;

    public $isWholeDay = 'true';

    public $leaveOptions = [];

    public $attachments;

    public $employee;

    public function mount(Request $request)
    {
        $this->leaveOptions = TenantCache::rememberForever('leave_types', function () {
            return Leave::selectOptions();
        });

        $this->leaveTypeId = array_key_first($this->leaveOptions);
        $this->dateFrom = $this->dateTo = now()->toDateString();

        $this->employee = Auth::user()->employee;

        $this->computeDays();
    }

    public function updatedDateFrom($value)
    {
        $this->computeDays();
    }

    public function updatedDateTo($value)
    {
        $this->computeDays();
    }

    public function updatedLeaveTypeId($value)
    {
        $this->computeDays();
    }

    public function updatedIsWholeDay($value)
    {
        $this->computeDays();
    }

    /**
     * Count scheduled working days, not calendar days, and split them into
     * paid and unpaid days using the employee's remaining credits.
     */
    public function computeDays()
    {
        if ($this->dateFrom == null || $this->dateTo == null) {
            return;
        }

        $from = CarbonImmutable::parse($this->dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($this->dateTo)->startOfDay();

        if ($to->lt($from)) {
            $this->daysWithPay = null;
            $this->daysWithoutPay = null;

            return;
        }

        $total = $this->workingDays($from, $to);

        if ($this->isWholeDay !== 'true') {
            $total = $from->equalTo($to) && $total > 0 ? 0.5 : $total;
        }

        $leave = Leave::query()->find($this->leaveTypeId);
        $code = strtoupper(trim((string) $leave?->code));

        if ($leave === null || in_array($code, self::UNPAID_CODES, true)) {
            $this->daysWithPay = 0;
            $this->daysWithoutPay = $total;

            return;
        }

        $balance = $this->availableBalance($code);

        $paid = min($total, $balance);

        $this->daysWithPay = $paid;
        $this->daysWithoutPay = max(0, $total - $paid);
    }

    public function applyLeave(Request $request)
    {
        if ($this->attachments != null) {
            $this->attachments = $this->attachments->store(path: 'attachments');
        }

        $this->validate([
            'leaveTypeId' => 'required',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
        ]);

        if ($this->isWholeDay !== 'true' && $this->dateFrom !== $this->dateTo) {
            $this->addError('dateTo', 'A half-day leave must start and end on the same date.');

            return;
        }

        $this->computeDays();

        $result = CreateLeaveAction::run($request, $this);

        if ($result === true) {
            return redirect()->route('portal.leaves');
        }
    }

    public function render()
    {
        return view('livewire.portal.leaves.create')
            ->title($this->title)->layout('layouts.portal');
    }

    /**
     * Prefer the ledger balance; fall back to the legacy employee column while
     * a company has not yet posted its opening balances.
     */
    private function availableBalance(string $code): float
    {
        if ($this->employee === null) {
            return 0;
        }

        $companyId = (int) ($this->employee->company_id ?? 0);
        $year = now()->year;

        $hasLedger = LeaveLedger::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $this->employee->getKey())
            ->where('leave_type_id', $this->leaveTypeId)
            ->where('period_year', $year)
            ->exists();

        if ($hasLedger) {
            return max(0, app(LeaveLedgerService::class)->balance(
                $companyId, (int) $this->employee->getKey(), (int) $this->leaveTypeId, $year,
            ));
        }

        return match ($code) {
            'VL' => (float) ($this->employee->total_vacation_leave ?? 0),
            'SL' => (float) ($this->employee->total_sick_leave ?? 0),
            default => PHP_FLOAT_MAX,
        };
    }

    private function workingDays(CarbonImmutable $from, CarbonImmutable $to): float
    {
        $daysYear = $this->employee?->payrollGroup?->days_year ?? PayrollGroup::NUMBER_WORKING_DAYS_MONFRI;
        $holidays = Holiday::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->all();

        $days = 0.0;

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            if ($daysYear === PayrollGroup::NUMBER_WORKING_DAYS_MONFRI && $date->isWeekend()) {
                continue;
            }

            if ($daysYear === PayrollGroup::NUMBER_WORKING_DAYS_MONSAT && $date->isSunday()) {
                continue;
            }

            if (in_array($date->toDateString(), $holidays, true)) {
                continue;
            }

            $days += 1;
        }

        return $days;
    }
}
