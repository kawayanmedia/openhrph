<?php

namespace App\Jurisdictions\Philippines\Importers;

use App\Jurisdictions\Contracts\StatutoryImporter;
use Illuminate\Support\Facades\Artisan;

/**
 * Descriptor for the Philippine statutory importer.
 *
 * The effective-dated rows are still written by payroll:statutory:import; this
 * object gives the installer and the admin a country-neutral entry point.
 */
final class PhilippinesStatutoryImporter implements StatutoryImporter
{
    public function code(): string
    {
        return 'ph.statutory';
    }

    public function supports(string $countryCode): bool
    {
        return ($countryCode |> trim(...) |> strtoupper(...)) === 'PH';
    }

    public function import(string $countryCode, array $options = []): array
    {
        $arguments = ['--country' => 'PH'];

        if (! empty($options['fresh'])) {
            $arguments['--fresh'] = true;
        }

        if (! empty($options['provisional'])) {
            $arguments['--provisional'] = true;
        }

        if (! empty($options['effective_from'])) {
            $arguments['--effective-from'] = (string) $options['effective_from'];
        }

        $exitCode = Artisan::call('payroll:statutory:import', $arguments);

        return [
            'driver' => 'ph',
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ];
    }
}
