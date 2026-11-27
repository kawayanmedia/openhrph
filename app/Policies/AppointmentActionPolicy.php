<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class AppointmentActionPolicy
{
    use TenantScopedAuthorization;
}
