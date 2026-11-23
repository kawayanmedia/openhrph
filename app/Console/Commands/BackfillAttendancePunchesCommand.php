<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForTenants;
use App\Models\AttendancePunch;
use App\Models\Timesheet;
use App\Services\Attendance\PunchLedger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Copy existing JSON punches into the append-only ledger.
 *
 * Safe to re-run: the ledger's unique key makes the backfill idempotent.
 */
class BackfillAttendancePunchesCommand extends Command
{
    use RunsForTenants;

    protected $signature = 'attendance:backfill-punches {--company= : Company id to run for} {--dry-run : Count without writing}';

    protected $description = 'Backfill the append-only punch ledger from existing timesheet logs';

    public function handle(): int
    {
        if (! Schema::hasTable('attendance_punches')) {
            $this->error('The attendance_punches table does not exist. Run migrations first.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totals = ['timesheets' => 0, 'punches' => 0, 'existing' => 0];
        $ledger = app(PunchLedger::class);

        $this->forTenantOption(function () use ($dryRun, $ledger, &$totals): void {
            Timesheet::query()
                ->whereNotNull('logs')
                ->with('employee')
                ->chunkById(500, function ($timesheets) use ($dryRun, $ledger, &$totals): void {
                    foreach ($timesheets as $timesheet) {
                        $logs = collect($timesheet->logs ?? [])
                            ->filter(fn ($log): bool => is_array($log) && isset($log['time']))
                            ->values();

                        if ($logs->isEmpty()) {
                            continue;
                        }

                        $totals['timesheets']++;

                        foreach ($logs as $log) {
                            $log['log_date'] ??= $timesheet->log_date?->toDateString();

                            $existed = $this->exists($timesheet, $log);

                            if ($existed) {
                                $totals['existing']++;

                                continue;
                            }

                            $totals['punches']++;

                            if (! $dryRun) {
                                $ledger->record($timesheet, $log);
                            }
                        }
                    }
                });
        });

        $this->info(sprintf(
            '%d timesheets scanned, %d punches %s, %d already present%s.',
            $totals['timesheets'],
            $totals['punches'],
            $dryRun ? 'to add' : 'added',
            $totals['existing'],
            $dryRun ? ' (dry run — nothing saved)' : '',
        ));

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function exists(Timesheet $timesheet, array $log): bool
    {
        $moment = isset($log['punched_at'])
            ? CarbonImmutable::parse((string) $log['punched_at'])
            : CarbonImmutable::parse(($log['log_date'] ?? $timesheet->log_date?->toDateString()).' '.$log['time']);

        return AttendancePunch::query()
            ->where('employee_id', $timesheet->employee_id)
            ->where('punched_at', $moment->toDateTimeString())
            ->where('direction', (int) ($log['log_type'] ?? 0) === 1 ? 1 : 0)
            ->exists();
    }
}
