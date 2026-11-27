<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class PersonnelActionPolicy
{
    use TenantScopedAuthorization;
}
