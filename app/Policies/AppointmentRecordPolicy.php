<?php

namespace App\Policies;

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class AppointmentRecordPolicy
{
    use TenantScopedAuthorization;

    public function apply(User $user, AppointmentRecord $appointment): bool
    {
        return $this->sameTenant($user, $appointment);
    }

    public function transition(User $user, AppointmentRecord $appointment): bool
    {
        return $this->sameTenant($user, $appointment);
    }
}
