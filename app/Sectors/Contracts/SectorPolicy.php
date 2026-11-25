<?php

namespace App\Sectors\Contracts;

use App\Enums\Sector;
use App\Sectors\Support\FeatureSet;
use App\Sectors\Support\SectorProfile;

/**
 * The stable contract every sector implements.
 *
 * Consumer code should depend on this interface, never on a concrete sector
 * class. A new sector can be added by providing another profile and class
 * without editing payroll, settings, menus or catalogues.
 */
interface SectorPolicy
{
    public function sector(): Sector;

    public function profile(): SectorProfile;

    public function label(): string;

    public function companyType(): string;

    /**
     * @return array<string, mixed>
     */
    public function statutory(): array;

    public function statutoryAgency(string $kind): ?string;

    /**
     * @return array<int, string>
     */
    public function contributionCodes(): array;

    /**
     * @return array<int, string>
     */
    public function excludedContributionCodes(): array;

    /**
     * @return array<int, string>
     */
    public function loanAgencies(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function employmentTypes(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function leaveTypes(): array;

    /**
     * @return array<string, mixed>
     */
    public function leaveRules(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compensationElements(): array;

    /**
     * @return array<string, mixed>
     */
    public function bonusScheme(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function documents(): array;

    /**
     * @return array<string, mixed>
     */
    public function remittances(): array;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array;

    public function supports(string $feature): bool;

    public function features(): FeatureSet;

    public function showsMenu(string $key): bool;

    /**
     * Screens, commands and reports this sector contributes.
     */
    public function featurePack(): SectorFeaturePack;

    /**
     * The social-insurance agency code from the profile (`sss` or `gsis`).
     */
    public function socialInsuranceAgency(): string;

    /**
     * Days of monetized leave that stay tax-exempt, or null for no limit.
     */
    public function leaveConversionTaxableDays(): ?int;
}
