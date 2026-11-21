<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds a new country module under app/Jurisdictions.
 */
class MakeJurisdiction extends Command
{
    protected $signature = 'openhr:make-jurisdiction {code : ISO 3166-1 alpha-2 country code}';

    protected $description = 'Create a new jurisdiction plugin module';

    public function handle(Filesystem $files): int
    {
        $code = strtoupper(trim((string) $this->argument('code')));

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            $this->error('The code must be a two-letter ISO 3166-1 code, for example MY.');

            return self::FAILURE;
        }

        $studly = Str::studly($code);
        $base = app_path('Jurisdictions/'.$studly);

        if (is_dir($base)) {
            $this->error('The module already exists: '.$base);

            return self::FAILURE;
        }

        foreach ([
            '',
            '/Payroll',
            '/Holidays/data',
            '/Config',
            '/Admin',
        ] as $directory) {
            $files->makeDirectory($base.$directory, 0755, true);
        }

        $files->put($base.'/'.$studly.'Jurisdiction.php', $this->jurisdictionStub($studly, $code));
        $files->put($base.'/Payroll/'.$studly.'PayrollPolicy.php', $this->policyStub($studly));
        $files->put($base.'/Holidays/'.$studly.'HolidayProvider.php', $this->holidayStub($studly, $code));
        $files->put($base.'/Holidays/data/calendar.php', "<?php\n\nreturn [\n    // '2026' => [\n    //     ['date' => '2026-01-01', 'name' => 'New Year', 'type' => 'regular', 'code' => 'rh'],\n    // ],\n];\n");
        $files->put($base.'/Config/sector.php', "<?php\n\nreturn ['profiles' => ['Government' => [], 'Private' => []]];\n");

        $this->info('Created '.$base);
        $this->line('Next: implement the providers in '.$studly.'Jurisdiction.php, then run openhr:jurisdiction:check '.$code.'.');

        return self::SUCCESS;
    }

    private function jurisdictionStub(string $studly, string $code): string
    {
        return <<<PHP
<?php

namespace App\\Jurisdictions\\{$studly};

use App\\Jurisdictions\\Support\\AbstractJurisdiction;
use App\\Jurisdictions\\{$studly}\\Holidays\\{$studly}HolidayProvider;
use App\\Jurisdictions\\{$studly}\\Payroll\\{$studly}PayrollPolicy;
use App\\Jurisdictions\\Contracts\\HolidayProvider;
use App\\Jurisdictions\\Contracts\\PayrollPolicy;

final class {$studly}Jurisdiction extends AbstractJurisdiction
{
    protected function defaultCode(): string
    {
        return '{$code}';
    }

    public function payrollPolicy(): PayrollPolicy
    {
        return new {$studly}PayrollPolicy;
    }

    public function holidays(): HolidayProvider
    {
        return new {$studly}HolidayProvider;
    }
}
PHP;
    }

    private function policyStub(string $studly): string
    {
        return <<<PHP
<?php

namespace App\\Jurisdictions\\{$studly}\\Payroll;

use App\\Jurisdictions\\Support\\PayrollPolicyData;

final class {$studly}PayrollPolicy extends PayrollPolicyData
{
    public function __construct()
    {
        parent::__construct(
            overtimeMultiplier: 1.25,
            nightDifferentialRate: 0.10,
        );
    }
}
PHP;
    }

    private function holidayStub(string $studly, string $code): string
    {
        return <<<PHP
<?php

namespace App\\Jurisdictions\\{$studly}\\Holidays;

use App\\Jurisdictions\\Contracts\\HolidayProvider;

final class {$studly}HolidayProvider implements HolidayProvider
{
    public function calendar(int \$year): array
    {
        \$calendar = require __DIR__.'/data/calendar.php';

        return (array) (\$calendar[(string) \$year] ?? []);
    }

    public function types(): array
    {
        return ['regular' => 'Regular holiday'];
    }

    public function import(int \$companyId, int \$year, bool \$fresh = false): int
    {
        // Persist the calendar once the module is configured.
        return 0;
    }
}
PHP;
    }
}
