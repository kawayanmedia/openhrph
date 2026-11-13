<?php

namespace App\Observers;

use App\Models\Leave;
use App\Support\Tenancy\TenantCache;
use Illuminate\Support\Facades\Cache;

class LeaveObserver
{
    /**
     * Handle the LeaveRequest "created" event.
     *
     * @return void
     */
    public function created(Leave $leave)
    {
        // Clear cache $request->user()->company_id. '_leave_types'
        TenantCache::forgetFor($leave->company_id, 'leave_types');
    }

    /**
     * Handle the LeaveRequest "updated" event.
     *
     * @return void
     */
    public function updated(Leave $leave)
    {
        // Clear cache $request->user()->company_id. '_leave_types'
        TenantCache::forgetFor($leave->company_id, 'leave_types');
    }

    /**
     * Handle the LeaveRequest "deleted" event.
     *
     * @return void
     */
    public function deleted(Leave $leave)
    {
        // Clear cache $request->user()->company_id. '_leave_types'
        TenantCache::forgetFor($leave->company_id, 'leave_types');
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
