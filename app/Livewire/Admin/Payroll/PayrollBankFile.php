<?php

namespace App\Livewire\Admin\Payroll;

use App\Models\BankFileTemplate;
use App\Models\CompanyBank;
use App\Models\Payroll;
use App\Services\Payroll\BankFileGenerator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class PayrollBankFile extends Component
{
    public int $payrollId;

    public ?int $templateId = null;

    public ?int $debitAccountId = null;

    public string $paymentDate = '';

    public function mount(Payroll $payroll): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
        abort_unless((int) $payroll->company_id === (int) Auth::user()?->company_id, 404);

        $this->payrollId = (int) $payroll->getKey();
        $this->templateId = BankFileTemplate::query()->where('is_active', true)->orderBy('name')->value('id');
        $this->debitAccountId = CompanyBank::query()->orderBy('name')->value('id');
        $this->paymentDate = ($payroll->payroll_date ?? now())->format('Y-m-d');
    }

    public function payroll(): Payroll
    {
        return Payroll::query()->findOrFail($this->payrollId);
    }

    public function render(BankFileGenerator $generator)
    {
        $payroll = $this->payroll();
        $templates = BankFileTemplate::query()->orderBy('name')->get();
        $accounts = CompanyBank::query()->orderBy('name')->get();
        $template = $templates->firstWhere('id', (int) $this->templateId);
        $account = $accounts->firstWhere('id', (int) $this->debitAccountId);

        $result = $template === null
            ? null
            : $generator->build($payroll, $template, $account, $this->paymentDate);

        return view('livewire.admin.payroll.bank-file', [
            'payroll' => $payroll,
            'templates' => $templates,
            'accounts' => $accounts,
            'template' => $template,
            'result' => $result,
        ])->title('Bank file · Payroll #'.$payroll->getKey());
    }
}
