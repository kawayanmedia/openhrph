<?php

namespace App\Jurisdictions\Generic;

use App\Jurisdictions\Contracts\PayrollPolicy;
use App\Jurisdictions\Contracts\StatutoryDriver;
use App\Jurisdictions\Support\AbstractJurisdiction;
use App\Jurisdictions\Support\PayrollPolicyData;

/**
 * Table/config-driven jurisdiction for countries without a dedicated module.
 */
final class GenericJurisdiction extends AbstractJurisdiction
{
    public static function fromConfig(string $code): self
    {
        $code = $code |> trim(...) |> strtoupper(...);
        $profile = config('payroll.countries.'.$code);

        if (! is_array($profile)) {
            $profile = [];
        }

        $profile['code'] = $code;

        return new self($profile);
    }

    protected function defaultCode(): string
    {
        return (string) config('jurisdictions.default', 'PH');
    }

    public function payrollPolicy(): PayrollPolicy
    {
        return PayrollPolicyData::fromArray(array_merge([
            'minimum_wage_tax_exempt' => false,
            'thirteenth_month_exempt_centavos' => 0,
        ], (array) ($this->profile['payroll_policy'] ?? [])));
    }

    public function statutoryDriver(): StatutoryDriver
    {
        return new GenericStatutoryDriver;
    }
}
