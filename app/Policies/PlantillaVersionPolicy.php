<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class PlantillaVersionPolicy
{
    use TenantScopedAuthorization;
}
