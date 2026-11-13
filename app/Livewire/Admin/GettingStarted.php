<?php

namespace App\Livewire\Admin;

use App\Services\Payroll\PayrollReadiness;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

#[Layout('layouts.admin')]
class GettingStarted extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
    }

    /**
     * Generate the open pay dates for the company's payroll group.
     */
    public function generatePayrollDates(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        if (! app(PayrollReadiness::class)->generatePayrollDates()) {
            session()->flash('admin.error', 'Create a payroll group before generating the pay schedule.');

            return;
        }

        session()->flash('admin.saved', 'Pay schedule generated.');
    }

    public function render()
    {
        return view('livewire.admin.getting-started', [
            'readiness' => app(PayrollReadiness::class)->summary(),
        ])->title('Getting started');
    }
}
