<?php

namespace App\Console\Commands;

use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\Engine\GoPayrollEngine;
use App\Services\Payroll\Engine\Parity\ParityCorpus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PayrollEngineCheck extends Command
{
    protected $signature = 'payroll:engine
        {--check : Exit non-zero when the engine is not ready}
        {--parity : Replay the shared parity corpus through the PHP driver}';

    protected $description = 'Verify the payroll engine binary, database connectivity and PHP/Go parity';

    public function handle(EngineManager $manager, GoPayrollEngine $engine): int
    {
        $this->line('Driver:  <info>'.$manager->driver().'</info>');
        $this->line('Binary:  '.config('payroll.engine.binary'));

        // Parity only needs the committed corpus, so it is checked before the
        // Go binary: a PHP-only host can still prove the driver matches.
        if ($this->option('parity') && ! $this->checkParity()) {
            return self::FAILURE;
        }

        $version = $engine->version();

        if ($version === null) {
            $this->error('The payroll engine binary is missing or not executable. Run `make -C payroll-engine build`.');

            return self::FAILURE;
        }

        $this->line('Version: <info>'.$version.'</info>');

        try {
            DB::connection()->getPdo();
            $this->line('Database: <info>connected</info>');
        } catch (Throwable $e) {
            $this->error('Database: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Payroll engine is ready.');

        return self::SUCCESS;
    }

    private function checkParity(): bool
    {
        $fixtures = ParityCorpus::fixtures();
        $mismatches = ParityCorpus::verify();

        if ($fixtures === []) {
            $this->error('Parity: no fixtures found in '.ParityCorpus::path());

            return false;
        }

        if ($mismatches !== []) {
            $this->error('Parity: the PHP driver diverges from the Go engine.');

            foreach (array_slice($mismatches, 0, 20) as $mismatch) {
                $this->line('  - '.$mismatch);
            }

            return false;
        }

        $this->line('Parity:  <info>'.count($fixtures).' fixtures match</info>');

        return true;
    }
}
