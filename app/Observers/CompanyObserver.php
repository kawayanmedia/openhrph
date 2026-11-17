<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     *
     * @return void
     */
    public function created(Company $company) {}

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
