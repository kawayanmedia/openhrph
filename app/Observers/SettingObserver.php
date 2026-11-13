<?php

namespace App\Observers;

use App\Models\Setting;
use App\Support\Tenancy\TenantContext;

class SettingObserver
{
    /**
     * Handle the Setting "created" event.
     *
     * @return void
     */
    public function created(Setting $setting)
    {
        $setting->company_id = app(TenantContext::class)->id() ?? $setting->company_id;
        $setting->save();
    }

    /**
     * Handle the Setting "updated" event.
     *
     * @return void
     */
    public function updated(Setting $setting)
    {
        //
    }

    /**
     * Handle the Setting "deleted" event.
     *
     * @return void
     */
    public function deleted(Setting $setting)
    {
        //
    }

    /**
     * Handle the Setting "restored" event.
     *
     * @return void
     */
    public function restored(Setting $setting)
    {
        //
    }

    /**
     * Handle the Setting "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Setting $setting)
    {
        //
    }
}
