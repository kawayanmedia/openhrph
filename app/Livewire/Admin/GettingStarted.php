<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use App\Services\Demo\DemoWorkspaceSeeder;
use App\Services\Desktop\DesktopEnvironment;
use App\Services\Payroll\PayrollReadiness;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
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

    /**
     * Fill an empty desktop workspace with the sample records the demo account
     * starts from.
     */
    public function loadSampleData(DemoWorkspaceSeeder $seeder): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
        abort_unless(DesktopEnvironment::isRunning(), 404);

        $company = $this->company();

        if ($company === null) {
            session()->flash('admin.error', 'Your account is not attached to a company.');

            return;
        }

        if ($seeder->hasCustomEmployees($company)) {
            session()->flash('admin.error', 'This company already has employees. Sample data is only for an empty workspace.');

            return;
        }

        $seeder->seed($company);

        session()->flash('admin.saved', 'Sample data loaded.');
    }

    public function render()
    {
        return view('livewire.admin.getting-started', [
            'readiness' => app(PayrollReadiness::class)->summary(),
            'canLoadSampleData' => $this->canLoadSampleData(),
        ])->title('Getting started');
    }

    private function canLoadSampleData(): bool
    {
        if (! DesktopEnvironment::isRunning()) {
            return false;
        }

        $company = $this->company();

        return $company !== null && ! app(DemoWorkspaceSeeder::class)->hasSampleData($company);
    }

    private function company(): ?Company
    {
        $companyId = Auth::user()?->company_id;

        return $companyId === null ? null : Company::query()->find($companyId);
    }
}
