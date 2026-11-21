<?php

use App\Services\Payroll\Engine\Legacy\WorkedHours;

dataset('punch lists', [
    'day shift with a break' => [
        [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '12:00:00', 'log_type' => 1],
            ['time' => '13:00:00', 'log_type' => 0],
            ['time' => '17:00:00', 'log_type' => 1],
        ],
        8.0,
    ],
    'overnight shift' => [
        [
            ['time' => '22:00:00', 'log_type' => 0],
            ['time' => '06:00:00', 'log_type' => 1],
        ],
        8.0,
    ],
    'single pair without seconds' => [
        [
            ['time' => '10:00', 'log_type' => 0],
            ['time' => '15:30', 'log_type' => 1],
        ],
        5.5,
    ],
    'missing time out' => [
        [
            ['time' => '08:00:00', 'log_type' => 0],
        ],
        0.0,
    ],
    'missing time in' => [
        [
            ['time' => '17:00:00', 'log_type' => 1],
        ],
        0.0,
    ],
    'empty list' => [[], 0.0],
]);

it('derives worked hours from the punch list', function (array $logs, float $expected) {
    expect(WorkedHours::fromLogs($logs))->toBe($expected);
})->with('punch lists');

it('ignores malformed punches', function () {
    expect(WorkedHours::fromLogs([
        ['time' => 'not-a-time', 'log_type' => 0],
        ['time' => '25:00', 'log_type' => 0],
        ['time' => '17:00:00', 'log_type' => 1],
    ]))->toBe(0.0);
});

it('falls back to the mirrored am/pm columns', function () {
    expect(WorkedHours::fromColumns('08:00', '12:00', '13:00', '17:00', null))->toBe(8.0)
        ->and(WorkedHours::fromColumns(null, null, '22:00', null, '06:00'))->toBe(8.0)
        ->and(WorkedHours::fromColumns('08:00', null, '13:00', '17:00', null))->toBe(4.0)
        ->and(WorkedHours::fromColumns(null, null, null, null, null))->toBe(0.0);
});

it('returns zero hours from a null or empty punch list', function () {
    expect(WorkedHours::fromLogs(null))->toBe(0.0)
        ->and(WorkedHours::fromLogs([]))->toBe(0.0);
});
