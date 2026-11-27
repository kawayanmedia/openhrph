<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ClearanceTemplatePolicy
{
    use TenantScopedAuthorization;
}
