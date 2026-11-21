<?php

namespace App\Jurisdictions\Philippines\Statutory;

use App\Jurisdictions\Contracts\StatutoryDriver;

/**
 * The built-in Philippine agency calculators (SSS, PhilHealth, Pag-IBIG,
 * GSIS and BIR withholding).
 */
final class PhilippinesStatutoryDriver implements StatutoryDriver
{
    public function code(): string
    {
        return 'ph';
    }

    public function contributions(array $context): array
    {
        return [
            'driver' => $this->code(),
            'agencies' => ['sss', 'philhealth', 'pagibig', 'gsis'],
            'components' => array_values((array) ($context['components'] ?? [])),
        ];
    }

    public function tax(array $context): array
    {
        return [
            'driver' => $this->code(),
            'agency' => 'bir',
            'tax_brackets' => array_values((array) ($context['tax_brackets'] ?? [])),
        ];
    }

    public function supports(string $runType): bool
    {
        return in_array($runType, ['regular', 'thirteenth_month', 'final_pay', 'annual_adjustment'], true);
    }
}
