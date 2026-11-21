<?php

namespace App\Jurisdictions\Support;

/**
 * Small static facade used where dependency injection is impractical.
 */
final class JurisdictionAccess
{
    public static function manager(): JurisdictionManager
    {
        return app(JurisdictionManager::class);
    }

    public static function current(): Contracts\Jurisdiction
    {
        return self::manager()->current();
    }

    public static function currencySymbol(): string
    {
        return self::current()->currencySymbol();
    }
}
