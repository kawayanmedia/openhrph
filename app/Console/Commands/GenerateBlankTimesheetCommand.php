<?php

namespace App\Console\Commands;

use App\Console\Concerns\GeneratesTimesheets;
use App\Console\Concerns\RunsForTenants;
use Illuminate\Console\Command;

class GenerateBlankTimesheetCommand extends Command
{
    use GeneratesTimesheets;
    use RunsForTenants;

    /**
     * Generate blank timesheet so that we can identify if the day is regular day,
     * rest day or holiday.
     *
     * @var string
     */
    protected $signature = 'blank_timesheet:generate {from_date?} {to_date?} {--company= : Company id to run for}';

    /**
     * Generate blank timesheet.
     *
     * @var string
     */
    protected $description = 'Generate blank timesheet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $range = $this->resolveTimesheetRange();

        if ($range === null) {
            return Command::FAILURE;
        }

        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $this->forTenantOption(function () use ($range, &$totals): void {
            $summary = $this->generateTimesheets($range['from'], $range['to']);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $summary[$key];
            }
        });

        $this->info(sprintf(
            'Timesheets %s to %s: %d created, %d updated, %d skipped (no schedule or already classified).',
            $range['from'],
            $range['to'],
            $totals['created'],
            $totals['updated'],
            $totals['skipped'],
        ));

        return Command::SUCCESS;
    }
}
