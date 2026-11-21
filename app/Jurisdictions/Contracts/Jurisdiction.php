<?php

namespace App\Jurisdictions\Contracts;

use App\Enums\Sector;
use Carbon\CarbonInterface;

/**
 * A country plugin.
 *
 * Resolve it through App\Jurisdictions\Support\JurisdictionManager; never
 * hardcode a country in payroll, menus, reports or settings.
 */
interface Jurisdiction
{
    public function code(): string;

    public function name(): string;

    public function currency(): string;

    public function currencySymbol(): string;

    public function locale(): string;

    public function timezone(): string;

    public function payrollPolicy(): PayrollPolicy;

    public function statutoryDriver(): StatutoryDriver;

    public function taxPolicy(): TaxPolicy;

    public function holidays(): HolidayProvider;

    public function leavePolicy(): LeavePolicyProvider;

    public function identifiers(): IdentifierProvider;

    public function documents(): DocumentProvider;

    public function reports(): ReportProvider;

    public function settings(): SettingsProvider;

    public function catalogue(): JurisdictionCatalogue;

    /**
     * The country-specific part of a Government or Private sector profile.
     *
     * @return array<string, mixed>
     */
    public function sectorProfile(Sector $sector): array;

    /**
     * @return array<string, StatutoryImporter>
     */
    public function importers(): array;

    /**
     * @return array<int, class-string>
     */
    public function adminResources(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminMenu(): array;

    /**
     * Optional service provider that registers the module's commands and routes.
     *
     * @return class-string|null
     */
    public function serviceProvider(): ?string;

    public function install(): void;

    public function verify(CarbonInterface $date): VerificationResult;
}
