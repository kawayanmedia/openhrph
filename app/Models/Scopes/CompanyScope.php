<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BelongsToTenant;

/**
 * @deprecated Use {@see TenantScope} via the {@see BelongsToTenant} trait.
 */
class CompanyScope extends TenantScope {}
