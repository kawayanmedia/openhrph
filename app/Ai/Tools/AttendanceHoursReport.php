<?php

namespace App\Ai\Tools;

use App\Services\Assistant\AttendanceHours;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AttendanceHoursReport implements Tool
{
    public function __construct(protected AttendanceHours $hours) {}

    public function name(): string
    {
        return 'attendance_hours_report';
    }

    public function description(): Stringable|string
    {
        return 'Report logged hours per employee for a period, lowest first. Use this for questions such as "who is under 80 hours?". When no period is given it uses the current open pay cut-off and states the exact dates it used.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $request->validate([
            'under_hours' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $period = $this->hours->period(
            $data['period_start'] ?? null,
            $data['period_end'] ?? null,
        );

        $threshold = isset($data['under_hours'])
            ? (float) $data['under_hours']
            : (float) config('assistant.default_under_hours');

        $report = $this->hours->report(
            $period,
            $threshold,
            (int) ($data['limit'] ?? config('assistant.max_result_rows')),
        );

        $report['threshold'] = $threshold;
        $report['note'] = $report['under_count'] === 0
            ? 'No employee is below the threshold in this window.'
            : $report['under_count'].' employee(s) are below the threshold in this window.';

        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'under_hours' => $schema->number()
                ->description('Only flag employees below this many total logged hours. Defaults to 80.'),
            'period_start' => $schema->string()
                ->description('Start date (Y-m-d). Omit to use the current pay cut-off.'),
            'period_end' => $schema->string()
                ->description('End date (Y-m-d). Omit to use the current pay cut-off.'),
            'limit' => $schema->integer()
                ->description('Maximum rows to return (default 30).')
                ->min(1)->max(50),
        ];
    }
}
