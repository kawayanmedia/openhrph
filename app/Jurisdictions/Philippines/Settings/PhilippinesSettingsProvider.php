<?php

namespace App\Jurisdictions\Philippines\Settings;

use App\Jurisdictions\Contracts\SettingsProvider;

final class PhilippinesSettingsProvider implements SettingsProvider
{
    public function countryFields(): array
    {
        return [
            ['key' => 'tin', 'label' => 'Company TIN', 'type' => 'text'],
            ['key' => 'rdo', 'label' => 'RDO Code', 'type' => 'text'],
            ['key' => 'sss', 'label' => 'SSS Employer Number', 'type' => 'text', 'sector' => 'Private'],
            ['key' => 'phil_health', 'label' => 'PhilHealth Employer Number', 'type' => 'text'],
            ['key' => 'hdmf', 'label' => 'Pag-IBIG Employer Number', 'type' => 'text'],
            ['key' => 'night_shift_differential_start', 'label' => 'Night differential start', 'type' => 'time'],
            ['key' => 'night_shift_differential_end', 'label' => 'Night differential end', 'type' => 'time'],
        ];
    }

    public function defaults(): array
    {
        return [
            'night_shift_differential_start' => '22:00',
            'night_shift_differential_end' => '06:00',
        ];
    }
}
