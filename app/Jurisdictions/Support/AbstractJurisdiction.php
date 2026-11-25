<?php

namespace App\Jurisdictions\Support;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\DocumentProvider;
use App\Jurisdictions\Contracts\HolidayProvider;
use App\Jurisdictions\Contracts\IdentifierProvider;
use App\Jurisdictions\Contracts\Jurisdiction;
use App\Jurisdictions\Contracts\JurisdictionCatalogue;
use App\Jurisdictions\Contracts\LeavePolicyProvider;
use App\Jurisdictions\Contracts\PayrollPolicy;
use App\Jurisdictions\Contracts\ReportProvider;
use App\Jurisdictions\Contracts\SettingsProvider;
use App\Jurisdictions\Contracts\StatutoryDriver;
use App\Jurisdictions\Contracts\TaxPolicy;
use App\Jurisdictions\Contracts\VerificationResult;
use App\Jurisdictions\Generic\GenericCatalogue;
use App\Jurisdictions\Generic\GenericDocumentProvider;
use App\Jurisdictions\Generic\GenericHolidayProvider;
use App\Jurisdictions\Generic\GenericIdentifierProvider;
use App\Jurisdictions\Generic\GenericLeavePolicyProvider;
use App\Jurisdictions\Generic\GenericReportProvider;
use App\Jurisdictions\Generic\GenericSettingsProvider;
use App\Jurisdictions\Generic\GenericStatutoryDriver;
use App\Jurisdictions\Generic\GenericTaxPolicy;
use Carbon\CarbonInterface;

/**
 * Profile-backed jurisdiction with generic providers.
 *
 * A country module overrides only the providers whose rules differ.
 */
abstract class AbstractJurisdiction implements Jurisdiction
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(protected readonly array $profile = []) {}

    public function code(): string
    {
        return strtoupper((string) ($this->profile['code'] ?? $this->defaultCode()));
    }

    abstract protected function defaultCode(): string;

    public function name(): string
    {
        return (string) ($this->profile['name'] ?? $this->code());
    }

    public function currency(): string
    {
        return strtoupper((string) ($this->profile['currency'] ?? 'USD'));
    }

    public function currencySymbol(): string
    {
        return (string) ($this->profile['currency_symbol'] ?? $this->currency());
    }

    public function locale(): string
    {
        return (string) ($this->profile['locale'] ?? 'en');
    }

    public function timezone(): string
    {
        return (string) ($this->profile['timezone'] ?? (string) config('app.timezone', 'UTC'));
    }

    public function payrollPolicy(): PayrollPolicy
    {
        return PayrollPolicyData::fromArray((array) ($this->profile['payroll_policy'] ?? []));
    }

    public function statutoryDriver(): StatutoryDriver
    {
        return new GenericStatutoryDriver;
    }

    public function taxPolicy(): TaxPolicy
    {
        return new GenericTaxPolicy($this->profile);
    }

    public function holidays(): HolidayProvider
    {
        return new GenericHolidayProvider($this->code(), $this->profile);
    }

    public function leavePolicy(): LeavePolicyProvider
    {
        return new GenericLeavePolicyProvider($this->profile);
    }

    public function identifiers(): IdentifierProvider
    {
        return new GenericIdentifierProvider($this->profile);
    }

    public function documents(): DocumentProvider
    {
        return new GenericDocumentProvider($this->profile);
    }

    public function reports(): ReportProvider
    {
        return new GenericReportProvider($this->profile);
    }

    public function settings(): SettingsProvider
    {
        return new GenericSettingsProvider($this->profile);
    }

    public function catalogue(): JurisdictionCatalogue
    {
        return new GenericCatalogue($this->profile);
    }

    public function sectorProfile(Sector $sector): array
    {
        return (array) ($this->profile['sectors'][$sector->value] ?? []);
    }

    public function importers(): array
    {
        return [];
    }

    public function sectorFeaturePack(Sector $sector): ?string
    {
        return null;
    }

    public function adminResources(): array
    {
        return [];
    }

    public function adminMenu(): array
    {
        return [];
    }

    public function serviceProvider(): ?string
    {
        return null;
    }

    public function install(): void {}

    public function verify(CarbonInterface $date): VerificationResult
    {
        return VerificationResult::success([$this->name().' uses the generic jurisdiction.']);
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->profile;
    }
}
