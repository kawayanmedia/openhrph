<?php

namespace App\Jurisdictions\Philippines\Identifiers;

use App\Jurisdictions\Contracts\IdentifierProvider;

final class PhilippinesIdentifierProvider implements IdentifierProvider
{
    public function employeeFields(): array
    {
        return [
            ['key' => 'tin_number', 'label' => 'TIN', 'required' => true],
            ['key' => 'sss_number', 'label' => 'SSS Number', 'required' => false, 'sector' => 'Private'],
            ['key' => 'gsis_number', 'label' => 'GSIS BP Number', 'required' => false, 'sector' => 'Government'],
            ['key' => 'philhealth_number', 'label' => 'PhilHealth Number', 'required' => true],
            ['key' => 'hdmf_number', 'label' => 'Pag-IBIG/HDMF Number', 'required' => true],
        ];
    }

    public function companyFields(): array
    {
        return [
            ['key' => 'tin', 'label' => 'TIN', 'required' => true],
            ['key' => 'rdo', 'label' => 'RDO Code', 'required' => true],
            ['key' => 'sss', 'label' => 'SSS Employer Number', 'required' => false, 'sector' => 'Private'],
            ['key' => 'phil_health', 'label' => 'PhilHealth Employer Number', 'required' => true],
            ['key' => 'hdmf', 'label' => 'Pag-IBIG Employer Number', 'required' => true],
        ];
    }

    public function required(): array
    {
        return ['tin_number', 'philhealth_number', 'hdmf_number'];
    }
}
