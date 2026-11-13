<?php

namespace App\Exceptions\Tenancy;

use RuntimeException;

class CrossTenantWriteException extends RuntimeException
{
    public static function forModel(string $model, int|string|null $value, ?int $tenantId): self
    {
        return new self(
            "Refusing to write [{$model}] with company_id [{$value}] while the active tenant is [{$tenantId}]."
        );
    }
}
