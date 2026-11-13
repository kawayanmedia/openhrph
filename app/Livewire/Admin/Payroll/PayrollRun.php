<?php

namespace App\Livewire\Admin\Payroll;

use App\Jobs\ProcessPayrollJob;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use Illuminate\Support\Facades\Auth;
use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\Engine\EngineResult;
use App\Services\Payroll\Engine\PayrollEngineException;
use App\Services\Payroll\PayrollAttendance;
use App\Services\Payroll\PayrollAudit;
use App\Services\Payroll\PayrollJournalService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class PayrollRun extends Component
{
    public int $payrollId;

    public string $status = 'open';

    /** @var array<int, array<string, mixed>> */
    public array $payslips = [];

    /** @var array<int, string> */
    public array $warnings = [];

    public ?string $error = null;

    public ?string $summary = null;

    public ?string $notice = null;

    public string $reopenReason = '';

    public string $overrideReason = '';

    public string $reviewNote = '';

    public string $approvalOverrideReason = '';

    public bool $previewed = false;

    public function mount(Payroll $payroll): void
    {
        $this->authorizePayroll($payroll);

        $this->payrollId = (int) $payroll->getKey();
        $this->status = (string) $payroll->status;

        if (in_array($this->status, [Payroll::STATUS_OPEN, 'draft', 'failed'], true)) {
            $this->preview();
        } elseif ($payroll->payrollDetails()->exists()) {
            $this->loadStoredDetails($payroll);
        }
    }

    public function payroll(): Payroll
    {
        return Payroll::query()->findOrFail($this->payrollId);
    }

    public function preview(?EngineManager $manager = null): void
    {
        $manager ??= app(EngineManager::class);

        $payroll = $this->payroll();

        try {
            $result = $manager->engine()->preview($payroll);
        } catch (PayrollEngineException $e) {
            $this->error = $e->getMessage();
            $this->previewed = true;

            return;
        }

        $this->applyResult($result);
        $this->summary = sprintf(
            '%d employees · gross ₱%s · deductions ₱%s · net ₱%s',
            $result->employees, $result->gross, $result->deductions, $result->net
        );
    }

    public function process(): void
    {
        $this->authorizePayroll($payroll = $this->payroll());

        if (in_array($payroll->status, [Payroll::STATUS_APPROVED, Payroll::STATUS_CLOSED], true)) {
            $this->error = 'This payroll is approved and locked. Reopen it before processing again.';

            return;
        }

        $attendance = app(PayrollAttendance::class);
        $missing = $attendance->missingEmployees($payroll);

        if ($missing->isNotEmpty() && $attendance->paysWithoutTimesheet((int) $payroll->company_id) && trim($this->overrideReason) === '') {
            $this->error = $missing->count().' employee(s) have no attendance for this period. Enter an override reason before processing.';

            return;
        }

        $attributes = [
            'status' => Payroll::STATUS_RUNNING,
            'failed_reason' => null,
            'processed_by' => auth()->id(),
        ];

        if ($missing->isNotEmpty()) {
            $attributes['override_reason'] = trim($this->overrideReason);
            $attributes['override_by'] = auth()->id();
            $attributes['override_at'] = now();
        }

        $payroll->forceFill($attributes)->save();

        if ($missing->isNotEmpty()) {
            PayrollAudit::record($payroll, 'no_attendance_override', trim($this->overrideReason), [
                'missing_employees' => $missing->modelKeys(),
            ]);
        }

        $this->status = Payroll::STATUS_RUNNING;

        ProcessPayrollJob::dispatchFor($payroll);

        $this->notice = 'Payroll processing started.';
        session()->flash('admin.action', 'Payroll processing started.');

        $this->redirectRoute('admin.payroll.run', ['payroll' => $payroll->getKey()], navigate: true);
    }

    public function review(): void
    {
        $payroll = $this->payroll();
        $this->authorizePayroll($payroll);

        if ($payroll->status !== Payroll::STATUS_PROCESSED) {
            $this->error = 'Only a processed payroll can be reviewed.';

            return;
        }

        $payroll->forceFill([
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        PayrollAudit::record($payroll, 'reviewed', trim($this->reviewNote) ?: null);

        $this->reviewNote = '';
        $this->notice = 'Payroll marked as reviewed.';
        session()->flash('admin.saved', 'Payroll marked as reviewed.');
    }

    public function approve(): void
    {
        $payroll = $this->payroll();

        $this->authorizePayroll($payroll);

        if ($payroll->status !== Payroll::STATUS_PROCESSED) {
            $this->error = 'Only a processed payroll can be approved.';

            return;
        }

        $needsReview = $payroll->reviewed_by === null;
        $sameUser = (int) $payroll->processed_by === (int) auth()->id();
        $selfReview = $payroll->reviewed_by !== null
            && (int) $payroll->reviewed_by === (int) $payroll->processed_by;

        if (($needsReview || $sameUser || $selfReview) && trim($this->approvalOverrideReason) === '') {
            $this->error = $needsReview
                ? 'This payroll has not been reviewed. Enter an override reason to approve without an independent review.'
                : ($selfReview
                    ? 'The processor reviewed this payroll. Enter an override reason or have a different reviewer sign off.'
                    : 'You processed this payroll. Enter an override reason to approve it without a different approver.');

            return;
        }

        if ($needsReview || $sameUser || $selfReview) {
            PayrollAudit::record($payroll, 'approval_segregation_override', trim($this->approvalOverrideReason), [
                'reviewed_by' => $payroll->reviewed_by,
                'processed_by' => $payroll->processed_by,
            ]);
        }

        $payroll->forceFill([
            'status' => Payroll::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ])->save();

        app(PayrollJournalService::class)->post($payroll);

        PayrollAudit::record($payroll, 'approved');

        $this->status = Payroll::STATUS_APPROVED;
        $this->notice = 'Payroll approved and locked.';
        session()->flash('admin.saved', 'Payroll approved and locked.');
    }

    public function reopen(): void
    {
        $payroll = $this->payroll();

        $this->authorizePayroll($payroll);

        if (! Auth::user()?->owner) {
            abort(403, 'Only the company owner can reopen an approved payroll.');
        }

        $reason = trim($this->reopenReason);

        if ($reason === '') {
            $this->error = 'Enter a reason before reopening an approved payroll.';

            return;
        }

        $payroll->forceFill([
            'status' => Payroll::STATUS_PROCESSED,
            'approved_at' => null,
            'approved_by' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'reopened_at' => now(),
            'reopened_by' => auth()->id(),
            'reopen_reason' => $reason,
        ])->save();

        app(PayrollJournalService::class)->reverse($payroll, $reason);

        PayrollAudit::record($payroll, 'reopened', $reason);

        $this->reopenReason = '';
        $this->status = Payroll::STATUS_PROCESSED;
        $this->notice = 'Payroll reopened for review.';
        session()->flash('admin.action', 'Payroll reopened for review.');
    }

    public function render()
    {
        $payroll = $this->payroll();

        return view('livewire.admin.payroll.payroll-run', [
            'payroll' => $payroll,
            'details' => $payroll->payrollDetails()->with('employee')->orderBy('id')->get(),
            'employees' => $this->payslips,
            'missingEmployees' => app(PayrollAttendance::class)->missingEmployees($payroll)->count(),
        ])->title('Payroll run');
    }

    protected function applyResult(EngineResult $result): void
    {
        $this->payslips = $result->payslips;
        $this->warnings = $result->warnings;
        $this->previewed = true;
        $this->error = null;
    }

    protected function loadStoredDetails(Payroll $payroll): void
    {
        $storedWarnings = is_array($payroll->warnings) ? $payroll->warnings : [];

        $detailWarnings = $payroll->payrollDetails()->get()
            ->flatMap(fn (PayrollDetail $detail) => is_array($detail->warnings) ? $detail->warnings : [])
            ->all();

        $this->warnings = array_values(array_unique(array_merge($storedWarnings, $detailWarnings)));

        $this->payslips = $payroll->payrollDetails()->with('employee')->get()->map(fn (PayrollDetail $detail) => [
            'employee_id' => $detail->employee_id,
            'employee_number' => $detail->employee?->employee_number,
            'name' => trim(($detail->employee?->first_name ?? '').' '.($detail->employee?->last_name ?? '')),
            'gross' => number_format($detail->gross_income, 2, '.', ''),
            'taxable_income' => number_format($detail->taxable_income, 2, '.', ''),
            'withholding_tax' => number_format($detail->withholding_tax, 2, '.', ''),
            'sss_employee' => number_format($detail->sss_employee + $detail->sss_provident_employee, 2, '.', ''),
            'philhealth_employee' => number_format($detail->philhealth_employee, 2, '.', ''),
            'pagibig_employee' => number_format($detail->hdmf_employee, 2, '.', ''),
            'total_deductions' => number_format($detail->total_deductions, 2, '.', ''),
            'net_pay' => number_format($detail->net_pay, 2, '.', ''),
        ])->all();

        $this->previewed = true;
    }

    protected function authorizePayroll(Payroll $payroll): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
        abort_unless((int) $payroll->company_id === (int) Auth::user()?->company_id, 404);
    }
}
