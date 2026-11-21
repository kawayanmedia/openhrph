<?php

namespace App\Jurisdictions\Support;

use App\Support\Money;

/**
 * Formats centavos with the installation's currency.
 *
 * UI code must use this instead of hardcoding a currency symbol.
 */
final class MoneyFormatter
{
    public static function format(int $centavos, ?string $symbol = null): string
    {
        $symbol = $symbol ?? JurisdictionAccess::currencySymbol();

        return $symbol.Money::format($centavos);
    }
}
