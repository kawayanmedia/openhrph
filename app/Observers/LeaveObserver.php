<?php

namespace App\Observers;

use App\Models\Leave;
use App\Support\Tenancy\TenantCache;
use Illuminate\Support\Facades\DB;

class LeaveObserver
{
    /**
     * Handle the LeaveRequest "created" event.
     *
     * @return void
     */
    public function created(Leave $leave)
    {
        $this->forgetCaches($leave);
    }

    /**
     * Handle the LeaveRequest "updated" event.
     *
     * @return void
     */
    public function updated(Leave $leave)
    {
        $this->forgetCaches($leave);
    }

    /**
     * Handle the LeaveRequest "deleted" event.
     *
     * @return void
     */
    public function deleted(Leave $leave)
    {
        $this->forgetCaches($leave);
    }

    /**
     * A system row changes for every company, so every tenant cache goes.
     */
    private function forgetCaches(Leave $leave): void
    {
        TenantCache::forget('leave_types');

        $companyId = $leave->getAttribute('company_id');

        if ($companyId !== null) {
            TenantCache::forgetFor((int) $companyId, 'leave_types');

            return;
        }

        TenantCache::forgetFor(null, 'leave_types');

        DB::table('companies')->orderBy('id')->pluck('id')->each(function ($id): void {
            TenantCache::forgetFor((int) $id, 'leave_types');
        });
    }

    /**
     * Handle the LeaveRequest "restored" event.
     *
     * @return void
     */
    public function restored(Leave $leaveRequest)
    {
        //
    }

    /**
     * Handle the LeaveRequest "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Leave $leaveRequest)
    {
        //
    }
}
