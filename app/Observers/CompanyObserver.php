<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Payroll\RateCatalogue;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     *
     * @return void
     */
    public function created(Company $company)
    {
        // A new workspace needs the day-type rates before anyone can record
        // attendance or compute a premium.
        RateCatalogue::sync((int) $company->getKey());
    }

    /**
     * Handle the Company "updated" event.
     *
     * @return void
     */
    public function updated(Company $company) {}

    /**
     * Handle the Company "deleted" event.
     *
     * @return void
     */
    public function deleted(Company $company)
    {
        // Delete also all employees and other related
        Employee::withoutTenant()->where('company_id', $company->id)->delete();
        User::query()->where('company_id', $company->id)->delete();
    }

    /**
     * Handle the Company "restored" event.
     *
     * @return void
     */
    public function restored(Company $company)
    {
        //
    }

    /**
     * Handle the Company "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Company $company)
    {
        //
    }
}
