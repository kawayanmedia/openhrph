<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class PlantillaItemPolicy
{
    use TenantScopedAuthorization;
}
