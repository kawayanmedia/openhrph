<?php

namespace App\Livewire\Admin\Payroll;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

#[Layout('layouts.admin')]
class PayrollPayslip extends Component
{
    public int $payrollId;

    public int $employeeId;

    public function mount(Payroll $payroll, Employee $employee): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
        abort_unless((int) $payroll->company_id === (int) Auth::user()?->company_id, 404);
        abort_unless((int) $employee->company_id === (int) $payroll->company_id, 404);

        abort_unless(
            PayrollDetail::query()
                ->where('payroll_id', $payroll->getKey())
                ->where('employee_id', $employee->getKey())
                ->exists(),
            404
        );

        $this->payrollId = (int) $payroll->getKey();
        $this->employeeId = (int) $employee->getKey();
    }

    public function render()
    {
        $payroll = Payroll::query()->findOrFail($this->payrollId);
        $employee = Employee::query()->findOrFail($this->employeeId);

        $detail = PayrollDetail::query()
            ->where('payroll_id', $payroll->getKey())
            ->where('employee_id', $employee->getKey())
            ->firstOrFail();

        return view('livewire.admin.payroll.payslip', [
            'payroll' => $payroll,
            'employee' => $employee,
            'detail' => $detail,
        ])->title('Payslip — '.trim($employee->first_name.' '.$employee->last_name));
    }
}
