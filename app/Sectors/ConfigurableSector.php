<?php

namespace App\Sectors;

use App\Enums\Sector;
use App\Sectors\Contracts\SectorPolicy;
use App\Sectors\Support\FeatureSet;
use App\Sectors\Support\SectorProfile;

/**
 * Default policy implementation backed entirely by config/sectors.php.
 *
 * GovernmentSector and PrivateSector extend this class. Override a method only
 * when the rule cannot be expressed as data; otherwise add it to the profile.
 */
abstract class ConfigurableSector implements SectorPolicy
{
    public function __construct(private readonly SectorProfile $profile) {}

    public function sector(): Sector
    {
        return $this->profile->sector;
    }

    public function profile(): SectorProfile
    {
        return $this->profile;
    }

    public function label(): string
    {
        return $this->profile->label();
    }

    public function companyType(): string
    {
        return $this->profile->companyType();
    }

    /**
     * @return array<string, mixed>
     */
    public function statutory(): array
    {
        return $this->profile->statutory();
    }

    public function statutoryAgency(string $kind): ?string
    {
        $value = $this->profile->get('statutory.'.$kind);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    public function contributionCodes(): array
    {
        return $this->strings('statutory.contribution_codes');
    }

    /**
     * @return array<int, string>
     */
    public function excludedContributionCodes(): array
    {
        return $this->strings('statutory.excluded_codes');
    }

    /**
     * @return array<int, string>
     */
    public function loanAgencies(): array
    {
        return $this->strings('statutory.loan_agencies');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function employmentTypes(): array
    {
        return $this->profile->employmentTypes();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function leaveTypes(): array
    {
        return $this->profile->leaveTypes();
    }

    /**
     * @return array<string, mixed>
     */
    public function leaveRules(): array
    {
        return $this->profile->leaveRules();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compensationElements(): array
    {
        return $this->profile->compensationElements();
    }

    /**
     * @return array<string, mixed>
     */
    public function bonusScheme(): array
    {
        return $this->profile->bonusScheme();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function documents(): array
    {
        return $this->profile->documents();
    }

    /**
     * @return array<string, mixed>
     */
    public function remittances(): array
    {
        return $this->profile->reports();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->profile->rules();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->profile->defaults();
    }

    public function supports(string $feature): bool
    {
        return $this->profile->supports($feature);
    }

    public function showsMenu(string $key): bool
    {
        return $this->profile->showsMenu($key);
    }

    public function features(): FeatureSet
    {
        return new FeatureSet($this->profile->features());
    }

    public function nightDifferentialWindow(): array
    {
        $window = $this->profile->get('rules.night_differential', []);

        return is_array($window) ? $window : [];
    }

    /**
     * @return array<int, string>
     */
    private function strings(string $path): array
    {
        $value = $this->profile->get($path, []);

        return array_values(array_map('strval', array_filter((array) $value, 'is_scalar')));
    }
}
