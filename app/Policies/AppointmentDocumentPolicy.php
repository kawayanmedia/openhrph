<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class AppointmentDocumentPolicy
{
    use TenantScopedAuthorization;
}
