<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use KawayanMedia\DtrPdf\Contract\SettingsInterface;

/**
 * DTR printing settings stored in the tenant `settings` table.
 */
final class SettingsProvider implements SettingsInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return get_setting($key, $default);
    }
}
