<?php

namespace App\Exceptions\Tenancy;

use RuntimeException;

class TenantNotResolvedException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self(
            "No tenant is resolved while querying [{$model}]. ".
            'Wrap the operation in TenantContext::run() or use the withoutTenant() scope.'
        );
    }
}
