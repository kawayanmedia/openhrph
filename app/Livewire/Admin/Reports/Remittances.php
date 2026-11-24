<?php

namespace App\Livewire\Admin\Reports;

use App\Models\Payroll;
use App\Models\PayrollRemittance;
use App\Services\Payroll\RemittanceReport;
use App\Services\Payroll\RemittanceSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
class Remittances extends Component
{
    #[Url]
    public ?int $payrollId = null;

    #[Url]
    public string $format = 'summary';

    #[Url]
    public string $agency = '';

    public bool $showReferenceModal = false;

    public ?int $modalRemittanceId = null;

    public string $modalMode = 'filed';

    public string $modalReference = '';

    public string $modalDate = '';

    public string $modalNotes = '';

    public function mount(?Payroll $payroll = null): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $this->payrollId = $payroll?->getKey() ?? $this->defaultPayrollId();
        $this->format = $this->defaultFormat();
    }

    public function updatedPayrollId(): void
    {
        $this->format = $this->defaultFormat();
        $this->agency = '';
    }

    public function generateSchedule(RemittanceSchedule $schedule): void
    {
        $payroll = $this->payroll();

        if ($payroll === null) {
            return;
        }

        $schedule->sync($payroll);

        session()->flash('admin.saved', 'Remittance schedule generated for payroll #'.$payroll->getKey().'.');
    }

    public function openReferenceModal(int $remittanceId, string $mode): void
    {
        abort_unless(in_array($mode, ['filed', 'paid'], true), 404);

        $remittance = $this->remittance($remittanceId);

        $this->modalRemittanceId = $remittance->getKey();
        $this->modalMode = $mode;
        $this->modalReference = (string) $remittance->reference_number;
        $this->modalDate = ($mode === 'paid'
            ? ($remittance->paid_at ?? now())
            : ($remittance->filed_at ?? now()))->format('Y-m-d');
        $this->modalNotes = (string) $remittance->notes;
        $this->showReferenceModal = true;
    }

    public function saveReference(RemittanceSchedule $schedule): void
    {
        $this->validate([
            'modalReference' => 'required|max:64',
            'modalDate' => 'required|date',
        ]);

        $remittance = $this->remittance((int) $this->modalRemittanceId);
        $date = Carbon::parse($this->modalDate);

        if ($this->modalMode === 'paid') {
            $schedule->markPaid($remittance, $date, $this->modalReference, Auth::id());
        } else {
            $schedule->markFiled($remittance, $this->modalReference, $date, Auth::id());
        }

        $remittance->forceFill(['notes' => $this->modalNotes !== '' ? $this->modalNotes : null])->save();

        $this->showReferenceModal = false;
        $this->modalRemittanceId = null;

        session()->flash('admin.saved', 'Remittance updated.');
    }

    public function void(int $remittanceId, RemittanceSchedule $schedule): void
    {
        $schedule->void($this->remittance($remittanceId), Auth::id());

        session()->flash('admin.saved', 'Remittance voided.');
    }

    public function closeReferenceModal(): void
    {
        $this->showReferenceModal = false;
        $this->modalRemittanceId = null;
    }

    public function render(RemittanceReport $report)
    {
        $payroll = $this->payroll();
        $formats = $report->formats();

        if (! array_key_exists($this->format, $formats)) {
            $this->format = $this->defaultFormat();
        }

        return view('livewire.admin.reports.remittances', [
            'payroll' => $payroll,
            'payrolls' => $this->payrolls(),
            'formats' => $formats,
            'summary' => $payroll === null ? [] : $report->agencySummary($payroll),
            'register' => $payroll === null ? collect() : PayrollRemittance::query()
                ->where('payroll_id', $payroll->getKey())
                ->orderBy('agency')
                ->get(),
            'preview' => $payroll === null ? [] : $report->rows($payroll, $this->format, ['agency' => $this->agency]),
            'isLoans' => $this->format === 'loans',
        ])->title('Government Remittances');
    }

    private function payroll(): ?Payroll
    {
        if ($this->payrollId === null) {
            return null;
        }

        return Payroll::query()->find($this->payrollId);
    }

    private function remittance(int $id): PayrollRemittance
    {
        $payroll = $this->payroll();

        abort_if($payroll === null, 404);

        return PayrollRemittance::query()
            ->where('payroll_id', $payroll->getKey())
            ->findOrFail($id);
    }

    private function defaultPayrollId(): ?int
    {
        return Payroll::query()
            ->whereIn('status', [Payroll::STATUS_PROCESSED, Payroll::STATUS_APPROVED, Payroll::STATUS_CLOSED])
            ->orderByDesc('payroll_date')
            ->value('id');
    }

    private function defaultFormat(): string
    {
        $formats = app(RemittanceReport::class)->formats();

        return array_key_exists('summary', $formats) ? 'summary' : (string) array_key_first($formats);
    }

    /**
     * @return Collection<int, Payroll>
     */
    private function payrolls()
    {
        return Payroll::query()
            ->whereIn('status', [Payroll::STATUS_PROCESSED, Payroll::STATUS_APPROVED, Payroll::STATUS_CLOSED])
            ->orderByDesc('payroll_date')
            ->limit(100)
            ->get();
    }
}
