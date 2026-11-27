<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ApplicationDocumentPolicy
{
    use TenantScopedAuthorization;
}
