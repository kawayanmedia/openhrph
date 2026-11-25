<?php

namespace App\Jurisdictions\Philippines;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\DocumentProvider;
use App\Jurisdictions\Contracts\HolidayProvider;
use App\Jurisdictions\Contracts\IdentifierProvider;
use App\Jurisdictions\Contracts\JurisdictionCatalogue;
use App\Jurisdictions\Contracts\LeavePolicyProvider;
use App\Jurisdictions\Contracts\PayrollPolicy;
use App\Jurisdictions\Contracts\ReportProvider;
use App\Jurisdictions\Contracts\SettingsProvider;
use App\Jurisdictions\Contracts\StatutoryDriver;
use App\Jurisdictions\Contracts\TaxPolicy;
use App\Jurisdictions\Contracts\VerificationResult;
use App\Jurisdictions\Philippines\Catalogues\PhilippinesCatalogue;
use App\Jurisdictions\Philippines\Documents\PhilippinesDocumentProvider;
use App\Jurisdictions\Philippines\Holidays\PhilippinesHolidayProvider;
use App\Jurisdictions\Philippines\Identifiers\PhilippinesIdentifierProvider;
use App\Jurisdictions\Philippines\Importers\PhilippinesHolidayImporter;
use App\Jurisdictions\Philippines\Importers\PhilippinesStatutoryImporter;
use App\Jurisdictions\Philippines\Leave\PhilippinesLeavePolicyProvider;
use App\Jurisdictions\Philippines\Payroll\PhilippinesPayrollPolicy;
use App\Jurisdictions\Philippines\Reports\PhilippinesReportProvider;
use App\Jurisdictions\Philippines\Settings\PhilippinesSettingsProvider;
use App\Jurisdictions\Philippines\Statutory\PhilippinesStatutoryDriver;
use App\Jurisdictions\Philippines\Tax\PhilippinesTaxPolicy;
use App\Jurisdictions\Philippines\Government\GovernmentFeaturePack;
use App\Jurisdictions\Philippines\Private\PrivateFeaturePack;
use App\Jurisdictions\Support\AbstractJurisdiction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Philippine jurisdiction: Labor Code, BIR, SSS, PhilHealth, Pag-IBIG, GSIS,
 * CSC leave and the Philippine holiday calendar.
 */
final class PhilippinesJurisdiction extends AbstractJurisdiction
{
    public function __construct()
    {
        $profile = array_merge([
            'name' => 'Philippines',
            'currency' => 'PHP',
            'currency_symbol' => '₱',
            'locale' => 'en_PH',
            'timezone' => 'Asia/Manila',
        ], (array) config('payroll.countries.PH', []));

        $profile['code'] = 'PH';

        parent::__construct($profile);
    }

    protected function defaultCode(): string
    {
        return 'PH';
    }

    public function payrollPolicy(): PayrollPolicy
    {
        return new PhilippinesPayrollPolicy;
    }

    public function statutoryDriver(): StatutoryDriver
    {
        return new PhilippinesStatutoryDriver;
    }

    public function taxPolicy(): TaxPolicy
    {
        return new PhilippinesTaxPolicy;
    }

    public function holidays(): HolidayProvider
    {
        return new PhilippinesHolidayProvider;
    }

    public function leavePolicy(): LeavePolicyProvider
    {
        return new PhilippinesLeavePolicyProvider;
    }

    public function identifiers(): IdentifierProvider
    {
        return new PhilippinesIdentifierProvider;
    }

    public function documents(): DocumentProvider
    {
        return new PhilippinesDocumentProvider;
    }

    public function reports(): ReportProvider
    {
        return new PhilippinesReportProvider;
    }

    public function settings(): SettingsProvider
    {
        return new PhilippinesSettingsProvider;
    }

    public function catalogue(): JurisdictionCatalogue
    {
        return new PhilippinesCatalogue;
    }

    public function sectorProfile(Sector $sector): array
    {
        $config = require __DIR__.'/Config/sector.php';

        return (array) ($config['profiles'][$sector->value] ?? []);
    }

    public function importers(): array
    {
        return [
            'statutory' => new PhilippinesStatutoryImporter,
            'holidays' => new PhilippinesHolidayImporter,
        ];
    }

    public function sectorFeaturePack(Sector $sector): ?string
    {
        return $sector === Sector::Government ? GovernmentFeaturePack::class : PrivateFeaturePack::class;
    }

    public function adminResources(): array
    {
        return [];
    }

    public function adminMenu(): array
    {
        $path = __DIR__.'/Admin/menu.php';

        return is_file($path) ? (array) require $path : [];
    }

    public function serviceProvider(): ?string
    {
        return PhilippinesServiceProvider::class;
    }

    public function install(): void {}

    public function verify(CarbonInterface $date): VerificationResult
    {
        $messages = [];

        if (! Schema::hasTable('statutory_sss_brackets')) {
            $messages[] = 'The statutory tables are missing. Run php artisan migrate.';
        }

        return $messages === []
            ? VerificationResult::success(['Philippine statutory tables are present.'])
            : VerificationResult::failure($messages);
    }
}
