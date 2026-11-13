<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForTenants;
use App\Models\Timesheet;
use Illuminate\Console\Command;

class GenerateTimesheetCommand extends Command
{
    use RunsForTenants;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timesheet:generate {from_date?} {to_date?} {--company= : Company id to run for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->forTenantOption(function (): void {
            $timesheet = new Timesheet;
            $timesheet->processImport();
        });

        return Command::SUCCESS;
    }
}
