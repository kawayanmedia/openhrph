<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForTenants;
use App\Models\Timesheet;
use App\Services\Attendance\TimesheetCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Recompute attendance for a date range.
 *
 * Use this after deploying a rule change or fixing a shift. Locked rows and
 * days without punches are left untouched. `--dry-run` reports the impact and
 * rolls the writes back.
 */
class RecomputeAttendanceCommand extends Command
{
    use RunsForTenants;

    protected $signature = 'attendance:recompute {from_date?} {to_date?} {--company= : Company id to run for} {--dry-run : Report changes without saving them}';

    protected $description = 'Recompute timesheet hours from raw punches for a date range';

    public function handle(): int
    {
        $range = $this->range();

        if ($range === null) {
            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totals = ['scanned' => 0, 'changed' => 0, 'skipped' => 0];
        $calculator = app(TimesheetCalculator::class);

        $this->forTenantOption(function () use ($range, $dryRun, $calculator, &$totals): void {
            if ($dryRun) {
                DB::beginTransaction();
            }

            try {
                Timesheet::query()
                    ->whereDate('log_date', '>=', $range['from'])
                    ->whereDate('log_date', '<=', $range['to'])
                    ->where('is_locked', false)
                    ->whereNotNull('logs')
                    ->with(['employee.payrollGroup', 'schedule'])
                    ->chunkById(500, function ($timesheets) use ($calculator, &$totals): void {
                        foreach ($timesheets as $timesheet) {
                            $totals['scanned']++;

                            if (collect($timesheet->logs ?? [])->isEmpty()) {
                                $totals['skipped']++;

                                continue;
                            }

                            $before = $this->snapshot($timesheet);
                            $calculator->compute($timesheet);

                            if ($this->snapshot($timesheet) !== $before) {
                                $totals['changed']++;
                            }
                        }
                    });
            } catch (Throwable $exception) {
                if ($dryRun) {
                    DB::rollBack();
                }

                throw $exception;
            }

            if ($dryRun) {
                DB::rollBack();
            }
        });

        $this->info(sprintf(
            '%s to %s: %d scanned, %d changed, %d skipped%s.',
            $range['from'],
            $range['to'],
            $totals['scanned'],
            $totals['changed'],
            $totals['skipped'],
            $dryRun ? ' (dry run — nothing saved)' : '',
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function range(): ?array
    {
        try {
            $from = $this->argument('from_date')
                ? Carbon::parse((string) $this->argument('from_date'))->toDateString()
                : now()->startOfMonth()->toDateString();
            $to = $this->argument('to_date')
                ? Carbon::parse((string) $this->argument('to_date'))->toDateString()
                : now()->endOfMonth()->toDateString();
        } catch (Throwable) {
            $this->error('Invalid date. Use YYYY-MM-DD.');

            return null;
        }

        if ($from > $to) {
            $this->error('The start date must be on or before the end date.');

            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    private function snapshot(Timesheet $timesheet): string
    {
        return json_encode([
            $timesheet->getRawOriginal('actual_regular'),
            $timesheet->getRawOriginal('actual_ot'),
            $timesheet->getRawOriginal('actual_nt'),
            $timesheet->getRawOriginal('actual_nt_ot'),
            $timesheet->getRawOriginal('paid_minutes'),
            $timesheet->getRawOriginal('late_minutes'),
            $timesheet->getRawOriginal('undertime_minutes'),
            $timesheet->getRawOriginal('rule_version'),
        ]);
    }
}
