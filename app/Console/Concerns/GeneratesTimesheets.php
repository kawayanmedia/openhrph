<?php

namespace App\Console\Concerns;

use App\Gateways\GenerateTimesheetGateway;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Throwable;

/**
 * Shared date-range handling for the blank timesheet generators.
 */
trait GeneratesTimesheets
{
    /**
     * Resolve the {from_date} / {to_date} arguments, defaulting to this month.
     *
     * @return array{from: string, to: string}|null
     */
    protected function resolveTimesheetRange(): ?array
    {
        try {
            $from = $this->argument('from_date');
            $to = $this->argument('to_date');
        } catch (Throwable) {
            $from = $to = null;
        }

        try {
            $from = is_string($from) && $from !== ''
                ? CarbonImmutable::parse($from)->toDateString()
                : now()->startOfMonth()->toDateString();

            $to = is_string($to) && $to !== ''
                ? CarbonImmutable::parse($to)->toDateString()
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

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function generateTimesheets(string $from, string $to): array
    {
        $dates = CarbonPeriod::create($from, '1 day', $to)->toArray();
        $gateway = new GenerateTimesheetGateway;
        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach (Employee::with('payrollGroup')->lazy() as $employee) {
            $summary = $gateway->addDays($employee, $dates);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $summary[$key];
            }
        }

        return $totals;
    }
}
