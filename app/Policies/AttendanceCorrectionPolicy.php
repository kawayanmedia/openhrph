<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class AttendanceCorrectionPolicy
{
    use TenantScopedAuthorization;
}
