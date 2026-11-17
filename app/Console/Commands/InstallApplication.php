<?php

namespace App\Console\Commands;

use App\Models\InstallationSetting;
use App\Models\Payroll;
use App\Services\Payroll\Statutory\DeductionCatalogue;
use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Configures a new installation: sector, country and payroll defaults.
 *
 * The sector is locked here so every company in the database (and every
 * company created later) is marked as Government or Private. The country
 * selects which statutory driver and profile the payroll engine uses.
 */
class InstallApplication extends Command
{
    protected $signature = 'openhr:install
        {--sector= : Government or private}
        {--country= : ISO 3166-1 alpha-2 country code}
        {--currency= : ISO 4217 currency code}
        {--locale= : Locale, for example en_PH}
        {--timezone= : Timezone, for example Asia/Manila}
        {--force : Reconfigure an existing installation}
        {--skip-statutory : Do not import the statutory schedule}';

    protected $description = 'Configure the installation sector, country and payroll defaults';

    public function handle(): int
    {
        $installed = Installation::installed();
        $existingCountry = Installation::countryCode();

        if ($installed && ! $this->option('force')) {
            $this->error('This installation is already configured. Re-run with --force to change it.');

            return self::FAILURE;
        }

        try {
            $sector = $this->resolveSector();
            $country = $this->resolveCountry();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($installed && $country !== $existingCountry) {
            if ($this->hasProcessedPayroll()) {
                $this->warn("This installation already has processed payrolls. Changing the country from {$existingCountry} to {$country} changes which statutory rows future runs read; historical runs stay on their original tables.");
            } else {
                $this->warn("Changing the country from {$existingCountry} to {$country} changes the statutory driver for future payroll runs.");
            }
        }

        $profile = Installation::countryProfile($country);

        $currency = strtoupper((string) ($this->option('currency') ?: ($profile['currency'] ?? 'PHP')));
        $locale = (string) ($this->option('locale') ?: ($profile['locale'] ?? 'en'));
        $timezone = (string) ($this->option('timezone') ?: ($profile['timezone'] ?? config('app.timezone', 'UTC')));

        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            $this->error('The country must be a two-letter ISO 3166-1 code, for example PH.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($sector, $country, $currency, $locale, $timezone): void {
            foreach ([
                Installation::KEY_SECTOR => $sector,
                Installation::KEY_COUNTRY => $country,
                Installation::KEY_CURRENCY => $currency,
                Installation::KEY_LOCALE => $locale,
                Installation::KEY_TIMEZONE => $timezone,
                Installation::KEY_INSTALLED_AT => now()->toDateTimeString(),
            ] as $key => $value) {
                InstallationSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $value],
                );
            }

            DB::table('companies')->update([
                'type' => $sector,
                'updated_at' => now(),
            ]);
        });

        Installation::forget();

        $companyCount = DB::table('companies')->count();

        $this->info("Installation configured for {$sector} organisations in {$country}.");
        $this->line("  Companies marked: {$companyCount}");
        $this->line("  Currency: {$currency} · Locale: {$locale} · Timezone: {$timezone}");

        $this->seedDeductions($country);

        if (! $this->option('skip-statutory')) {
            $this->importStatutory($country);
        }

        return self::SUCCESS;
    }

    private function resolveSector(): string
    {
        $option = $this->option('sector');

        if (is_string($option) && trim($option) !== '') {
            $sector = Installation::normalizeSector($option);

            if ($sector === null) {
                throw new \RuntimeException('The sector must be Government or Private.');
            }

            return $sector;
        }

        if (! $this->input->isInteractive()) {
            throw new \RuntimeException('The sector is required. Pass --sector=Government or --sector=Private.');
        }

        $choice = $this->choice(
            'Will this installation be used by Government or Private organisations?',
            [Installation::SECTOR_GOVERNMENT, Installation::SECTOR_PRIVATE],
            Installation::SECTOR_PRIVATE,
        );

        return Installation::normalizeSector((string) $choice) ?? Installation::SECTOR_PRIVATE;
    }

    private function resolveCountry(): string
    {
        /** @var array<string, array<string, mixed>> $countries */
        $countries = (array) config('payroll.countries', []);

        $option = $this->option('country');

        if (is_string($option) && trim($option) !== '') {
            $code = strtoupper(trim($option));

            if (! array_key_exists($code, $countries)) {
                throw new \RuntimeException("Unknown country [{$code}]. Add it to config/payroll.php first.");
            }

            return $code;
        }

        if (! $this->input->isInteractive()) {
            throw new \RuntimeException('The country is required. Pass --country=PH (or another configured code).');
        }

        $choices = [];

        foreach ($countries as $code => $profile) {
            $choices[] = $code.' — '.($profile['name'] ?? $code);
        }

        $answer = (string) $this->choice('Which country payroll rules should be used?', $choices, 0);

        return strtoupper(substr($answer, 0, 2));
    }

    private function hasProcessedPayroll(): bool
    {
        return Payroll::withoutTenant()
            ->whereIn('status', [Payroll::STATUS_PROCESSED, Payroll::STATUS_APPROVED, Payroll::STATUS_CLOSED])
            ->exists();
    }

    private function seedDeductions(string $country): void
    {
        $total = 0;

        DB::table('companies')->orderBy('id')->pluck('id')->each(function ($companyId) use ($country, &$total): void {
            $total += DeductionCatalogue::seedDefaults((int) $companyId, $country);
        });

        if ($total > 0) {
            $this->line("  Deduction templates created: {$total}");
        }
    }

    private function importStatutory(string $country): void
    {
        $exitCode = Artisan::call('payroll:statutory:import', ['--country' => $country]);

        if ($exitCode !== self::SUCCESS) {
            $this->warn('The statutory import did not complete. Run `php artisan payroll:statutory:import --country='.$country.'` to retry.');
        }

        $output = trim(Artisan::output());

        if ($output !== '') {
            $this->line($output);
        }
    }
}
