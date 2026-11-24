<?php

namespace App\Support\Installation;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\Jurisdiction;
use App\Jurisdictions\Support\JurisdictionManager;
use App\Models\InstallationSetting;
use App\Sectors\Contracts\SectorPolicy;
use App\Sectors\SectorManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads and writes installation-wide configuration.
 *
 * A missing `sector` row means the installation has not chosen Government or
 * Private yet; in that mode company types stay editable and payroll behaves
 * exactly as before. Once a sector is set, every company is pinned to it.
 */
final class Installation
{
    public const SECTOR_GOVERNMENT = 'Government';

    public const SECTOR_PRIVATE = 'Private';

    public const KEY_SECTOR = 'sector';

    public const KEY_COUNTRY = 'country_code';

    public const KEY_CURRENCY = 'currency_code';

    public const KEY_LOCALE = 'locale';

    public const KEY_TIMEZONE = 'timezone';

    public const KEY_INSTALLED_AT = 'installed_at';

    private const CACHE_KEY = 'installation.settings';

    /**
     * All installation settings keyed by name.
     *
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        try {
            if (! Schema::hasTable('installation_settings')) {
                return [];
            }

            $load = fn (): array => InstallationSetting::query()
                ->orderBy('id')
                ->get(['key', 'value'])
                ->mapWithKeys(fn (InstallationSetting $setting): array => [(string) $setting->key => $setting->value])
                ->all();

            // Caching would leak installation settings between tests that roll back
            // their database transaction, so the test environment reads directly.
            if (app()->runningUnitTests()) {
                return $load();
            }

            /** @var array<string, string|null> $settings */
            $settings = Cache::rememberForever(self::CACHE_KEY, $load);

            return $settings;
        } catch (QueryException) {
            // The application boots before a database may exist, for example
            // during `composer dump-autoload` / `artisan package:discover` in
            // CI. Treat an unavailable database as "not configured yet".
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        self::setMany([$key => $value]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                InstallationSetting::query()->updateOrCreate(
                    ['key' => (string) $key],
                    ['value' => $value === null ? null : (string) $value],
                );
            }
        });

        self::forget();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function installed(): bool
    {
        return self::get(self::KEY_INSTALLED_AT) !== null;
    }

    /**
     * The locked installation sector, or null when the installer has not run.
     */
    public static function sector(): ?string
    {
        return self::sectorEnum()?->value;
    }

    /**
     * The locked sector as an enum, or null when the installer has not run.
     */
    public static function sectorEnum(): ?Sector
    {
        $sector = self::get(self::KEY_SECTOR);

        if (! is_string($sector) || trim($sector) === '') {
            return null;
        }

        return Sector::tryFromValue($sector);
    }

    /**
     * The active sector policy. Falls back to Private before installation.
     */
    public static function sectorPolicy(): SectorPolicy
    {
        return app(SectorManager::class)->current();
    }

    /**
     * The active sector profile as a plain array.
     *
     * @return array<string, mixed>
     */
    public static function sectorProfile(): array
    {
        return self::sectorPolicy()->profile()->toArray();
    }

    public static function isGovernment(): bool
    {
        return self::sectorEnum()?->isGovernment() ?? false;
    }

    public static function normalizeSector(string $sector): ?string
    {
        return Sector::tryFromValue($sector)?->value;
    }

    public static function countryCode(): string
    {
        $code = self::get(self::KEY_COUNTRY);

        if (! is_string($code) || trim($code) === '') {
            $code = (string) config('jurisdictions.default', config('payroll.default_country', 'PH'));
        }

        return $code |> trim(...) |> strtoupper(...);
    }

    /**
     * The country profile from config/payroll.php.
     *
     * @return array<string, mixed>
     */
    public static function countryProfile(?string $code = null): array
    {
        $code = (string) ($code ?? self::countryCode()) |> trim(...) |> strtoupper(...);

        $profile = config('payroll.countries.'.$code);

        if (! is_array($profile)) {
            $jurisdiction = app(JurisdictionManager::class)->for($code);
            $profile = $jurisdiction->profile();

            if ($profile === []) {
                $profile = [
                    'name' => $jurisdiction->name(),
                    'currency' => $jurisdiction->currency(),
                    'currency_symbol' => $jurisdiction->currencySymbol(),
                    'locale' => $jurisdiction->locale(),
                    'timezone' => $jurisdiction->timezone(),
                ];
            }
        }

        return is_array($profile) ? $profile : [];
    }

    /**
     * The active country plugin.
     */
    public static function jurisdiction(?string $code = null): Jurisdiction
    {
        return app(JurisdictionManager::class)->for($code ?? self::countryCode());
    }

    public static function statutoryDriver(?string $code = null): string
    {
        return self::jurisdiction($code)->statutoryDriver()->code();
    }

    /**
     * A version tag written on every payroll so a historical run can be
     * reproduced even after the installation changes country or rates.
     */
    public static function policyVersion(?string $code = null): string
    {
        $code = (string) ($code ?? self::countryCode()) |> trim(...) |> strtoupper(...);

        return $code.':'.self::statutoryDriver($code).':v1';
    }

    public static function currencyCode(): string
    {
        return (string) (self::get(self::KEY_CURRENCY) ?: (self::countryProfile()['currency'] ?? 'PHP'));
    }

    public static function currencySymbol(): string
    {
        return (string) (self::countryProfile()['currency_symbol'] ?? self::currencyCode());
    }

    public static function locale(): string
    {
        return (string) (self::get(self::KEY_LOCALE) ?: (self::countryProfile()['locale'] ?? 'en'));
    }

    public static function timezone(): string
    {
        return (string) (self::get(self::KEY_TIMEZONE) ?: (self::countryProfile()['timezone'] ?? (string) config('app.timezone', 'UTC')));
    }
}
