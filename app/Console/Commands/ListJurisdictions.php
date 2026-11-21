<?php

namespace App\Console\Commands;

use App\Jurisdictions\Support\JurisdictionManager;
use App\Support\Installation\Installation;
use Illuminate\Console\Command;

class ListJurisdictions extends Command
{
    protected $signature = 'openhr:jurisdictions';

    protected $description = 'List the registered country plugins and the active installation jurisdiction';

    public function handle(JurisdictionManager $jurisdictions): int
    {
        $rows = [];
        $all = $jurisdictions->all();

        foreach ((array) config('payroll.countries', []) as $code => $profile) {
            if (! isset($all[$code])) {
                $all[$code] = $jurisdictions->for($code);
            }
        }

        foreach ($all as $code => $jurisdiction) {
            $rows[] = [
                $code,
                $jurisdiction->name(),
                $jurisdiction->currency(),
                $jurisdiction->timezone(),
                $jurisdiction->statutoryDriver()->code(),
                $code === Installation::countryCode() ? 'active' : '',
            ];
        }

        $this->table(['Code', 'Name', 'Currency', 'Timezone', 'Driver', 'Active'], $rows);

        return self::SUCCESS;
    }
}
