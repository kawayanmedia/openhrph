<?php

use App\Services\Payroll\Engine\EngineResult;

it('maps every field from an engine payload', function () {
    $result = EngineResult::fromArray([
        'payroll_id' => 41,
        'employees' => 12,
        'gross' => '152340.55',
        'deductions' => '18234.10',
        'net' => '134106.45',
        'payslips' => [['employee_id' => 7, 'net_pay' => '11250.00']],
        'warnings' => ['employee 7: no attendance recorded'],
        'duration_ms' => 830,
        'engine_version' => 'hris-payroll-engine/1.4.0',
        'dry_run' => true,
        'revision' => 3,
    ]);

    expect($result->payrollId)->toBe(41)
        ->and($result->employees)->toBe(12)
        ->and($result->gross)->toBe('152340.55')
        ->and($result->deductions)->toBe('18234.10')
        ->and($result->net)->toBe('134106.45')
        ->and($result->payslips)->toBe([['employee_id' => 7, 'net_pay' => '11250.00']])
        ->and($result->warnings)->toBe(['employee 7: no attendance recorded'])
        ->and($result->durationMs)->toBe(830)
        ->and($result->engineVersion)->toBe('hris-payroll-engine/1.4.0')
        ->and($result->dryRun)->toBeTrue()
        ->and($result->revision)->toBe(3);
});

it('falls back to safe defaults when the payload is partial', function () {
    $result = EngineResult::fromArray(['payroll_id' => 9]);

    expect($result->employees)->toBe(0)
        ->and($result->gross)->toBe('0.00')
        ->and($result->deductions)->toBe('0.00')
        ->and($result->net)->toBe('0.00')
        ->and($result->payslips)->toBe([])
        ->and($result->warnings)->toBe([])
        ->and($result->durationMs)->toBe(0)
        ->and($result->engineVersion)->toBe('unknown')
        ->and($result->dryRun)->toBeFalse()
        ->and($result->revision)->toBe(0);
});

it('ignores payslips and warnings that are not lists', function () {
    $result = EngineResult::fromArray([
        'payroll_id' => 9,
        'payslips' => 'not-a-list',
        'warnings' => null,
    ]);

    expect($result->payslips)->toBe([])
        ->and($result->warnings)->toBe([]);
});

it('round-trips through toArray without losing a value', function () {
    $payload = [
        'payroll_id' => 41,
        'employees' => 2,
        'gross' => '26000.00',
        'deductions' => '4000.00',
        'net' => '22000.00',
        'payslips' => [['employee_id' => 1]],
        'warnings' => [],
        'duration_ms' => 120,
        'engine_version' => 'test',
        'dry_run' => false,
        'revision' => 1,
    ];

    expect(EngineResult::fromArray($payload)->toArray())->toBe($payload);
});
