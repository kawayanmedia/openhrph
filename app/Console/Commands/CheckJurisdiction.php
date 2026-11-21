<?php

namespace App\Console\Commands;

use App\Jurisdictions\Support\JurisdictionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckJurisdiction extends Command
{
    protected $signature = 'openhr:jurisdiction:check {code : ISO 3166-1 alpha-2 country code}';

    protected $description = 'Validate a jurisdiction plugin contract and its configured data';

    public function handle(JurisdictionManager $jurisdictions): int
    {
        $code = strtoupper(trim((string) $this->argument('code')));
        $jurisdiction = $jurisdictions->for($code);
        $errors = [];

        if ($jurisdiction->code() !== $code) {
            $errors[] = "The jurisdiction resolved to {$jurisdiction->code()} instead of {$code}.";
        }

        if ($jurisdiction->currency() === '') {
            $errors[] = 'Currency is empty.';
        }

        if ($jurisdiction->timezone() === '') {
            $errors[] = 'Timezone is empty.';
        }

        if ($jurisdiction->statutoryDriver()->code() === '') {
            $errors[] = 'Statutory driver code is empty.';
        }

        $result = $jurisdiction->verify(Carbon::now());

        if (! $result->ok) {
            $errors = array_merge($errors, $result->messages);
        }

        $this->line('Code:      '.$jurisdiction->code());
        $this->line('Name:      '.$jurisdiction->name());
        $this->line('Driver:    '.$jurisdiction->statutoryDriver()->code());
        $this->line('Currency:  '.$jurisdiction->currency().' ('.$jurisdiction->currencySymbol().')');
        $this->line('Timezone:  '.$jurisdiction->timezone());
        $this->line('Policy:    '.count($jurisdiction->payrollPolicy()->specialRuns()).' special run types');
        $this->line('Holidays:  '.count($jurisdiction->holidays()->calendar((int) now()->year)).' rows for '.now()->year);

        if ($errors !== []) {
            $this->newLine();

            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Jurisdiction contract is valid.');

        return self::SUCCESS;
    }
}
