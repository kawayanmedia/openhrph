<?php

use App\Models\Payroll;
use App\Services\Payroll\Engine\GoPayrollEngine;
use App\Services\Payroll\Engine\PayrollEngineException;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('The fake engine binary is a POSIX shell script.');
    }
});

/**
 * Builds a fake engine binary that records its arguments and prints a payload,
 * so the process boundary can be tested without the real Go binary or MySQL.
 */
function fakeEngineBinary(?string $payload = null, int $exitCode = 0, string $stderr = ''): array
{
    $directory = sys_get_temp_dir().'/hris-engine-'.bin2hex(random_bytes(6));
    (new Filesystem)->mkdir($directory);

    $arguments = $directory.'/arguments.txt';
    $stdout = $directory.'/stdout.txt';
    $error = $directory.'/stderr.txt';

    $script = <<<SH
    #!/bin/sh
    printf '%s\n' "\$@" > '{$arguments}'

    if [ "\$1" = "--version" ]; then
        printf '%s' 'hris-payroll-engine 1.4.0'
        exit {$exitCode}
    fi

    cat > /dev/null 2>&1
    printf '%s' '{$payload}' > '{$stdout}'
    printf '%s' '{$stderr}' > '{$error}'
    cat '{$stdout}'
    cat '{$error}' >&2
    exit {$exitCode}
    SH;

    $binary = $directory.'/payroll-engine';
    file_put_contents($binary, $script);
    chmod($binary, 0755);

    return [$binary, $arguments, $directory];
}

function engineTestPayroll(int $id = 7): Payroll
{
    return Payroll::factory()->make([
        'id' => $id,
        'company_id' => 1,
        'payroll_group_id' => 1,
    ]);
}

it('reports the engine as unavailable when the binary is missing', function () {
    $engine = new GoPayrollEngine('/nonexistent/payroll-engine', 'user:pass@tcp(127.0.0.1:3306)/hris');

    expect($engine->isAvailable())->toBeFalse()
        ->and($engine->version())->toBeNull();
});

it('reports the engine as unavailable when the binary is not executable', function () {
    $path = sys_get_temp_dir().'/hris-not-executable-'.bin2hex(random_bytes(4));
    file_put_contents($path, '#!/bin/sh');
    chmod($path, 0644);

    $engine = new GoPayrollEngine($path, 'user:pass@tcp(127.0.0.1:3306)/hris');

    expect($engine->isAvailable())->toBeFalse();

    unlink($path);
});

it('fails with an actionable message when the binary is missing', function () {
    $engine = new GoPayrollEngine('/nonexistent/payroll-engine', 'dsn');

    expect(fn () => $engine->process(engineTestPayroll()))
        ->toThrow(PayrollEngineException::class, 'The payroll engine binary was not found');
});

it('passes the payroll id, dsn and dry-run flag for a preview', function () {
    [$binary, $arguments, $directory] = fakeEngineBinary(enginePayloadJson());

    $result = (new GoPayrollEngine($binary, 'user:pass@tcp(db:3306)/hris'))
        ->preview(engineTestPayroll(7));

    $args = file($arguments, FILE_IGNORE_NEW_LINES);

    expect($args)->toContain('--payroll-id=7')
        ->and($args)->toContain('--json')
        ->and($args)->toContain('--dsn=user:pass@tcp(db:3306)/hris')
        ->and($args)->toContain('--dry-run')
        ->and($args)->not->toContain('--force')
        ->and($result->dryRun)->toBeTrue()
        ->and($result->payrollId)->toBe(7);

    (new Filesystem)->remove($directory);
});

it('passes the force flag instead of dry-run when processing', function () {
    [$binary, $arguments, $directory] = fakeEngineBinary(enginePayloadJson(dryRun: false));

    (new GoPayrollEngine($binary, 'dsn'))->process(engineTestPayroll(7), force: true);

    $args = file($arguments, FILE_IGNORE_NEW_LINES);

    expect($args)->toContain('--force')
        ->and($args)->not->toContain('--dry-run');

    (new Filesystem)->remove($directory);
});

it('returns the parsed payslips and totals from the engine', function () {
    [$binary, , $directory] = fakeEngineBinary(enginePayloadJson());

    $result = (new GoPayrollEngine($binary, 'dsn'))->preview(engineTestPayroll(7));

    // 13,050.00 basic + 375.00 overtime + 30.00 night - 1,600.00 deductions.
    expect($result->gross)->toBe('13455.00')
        ->and($result->deductions)->toBe('1600.00')
        ->and($result->net)->toBe('11855.00')
        ->and($result->employees)->toBe(1)
        ->and($result->payslips)->toHaveCount(1)
        ->and($result->payslips[0]['employee_no'])->toBe('E-0007')
        ->and($result->engineVersion)->toBe('test-engine');

    (new Filesystem)->remove($directory);
});

it('surfaces the engine error output when the process fails', function () {
    [$binary, , $directory] = fakeEngineBinary('', exitCode: 2, stderr: 'sss: no statutory bracket matches the salary');

    expect(fn () => (new GoPayrollEngine($binary, 'dsn'))->process(engineTestPayroll()))
        ->toThrow(PayrollEngineException::class, 'sss: no statutory bracket matches the salary');

    (new Filesystem)->remove($directory);
});

it('rejects a response that is not valid json', function () {
    [$binary, , $directory] = fakeEngineBinary('{"payroll_id": ');

    expect(fn () => (new GoPayrollEngine($binary, 'dsn'))->process(engineTestPayroll()))
        ->toThrow(PayrollEngineException::class, 'invalid response');

    (new Filesystem)->remove($directory);
});

it('reports the engine version from the version flag', function () {
    [$binary, , $directory] = fakeEngineBinary(enginePayloadJson());

    expect((new GoPayrollEngine($binary, 'dsn'))->version())
        ->toBe('hris-payroll-engine 1.4.0');

    (new Filesystem)->remove($directory);
});

it('returns no version when the version check fails', function () {
    [$binary, , $directory] = fakeEngineBinary(enginePayloadJson(), exitCode: 1);

    // The version command exits non-zero, so the engine reports no version.
    expect((new GoPayrollEngine($binary, 'dsn'))->version())->toBeNull();

    (new Filesystem)->remove($directory);
});

/**
 * A representative engine payload matching the semi-monthly scenario the Go
 * suite asserts: basic 13,050.00, overtime 375.00, night 30.00, deductions
 * 1,600.00, net 11,855.00.
 */
function enginePayloadJson(bool $dryRun = true): string
{
    return json_encode([
        'payroll_id' => 7,
        'employees' => 1,
        'gross' => '13455.00',
        'deductions' => '1600.00',
        'net' => '11855.00',
        'payslips' => [[
            'employee_id' => 1,
            'employee_no' => 'E-0007',
            'gross' => '13455.00',
            'total_deductions' => '1600.00',
            'net_pay' => '11855.00',
        ]],
        'warnings' => [],
        'duration_ms' => 42,
        'engine_version' => 'test-engine',
        'dry_run' => $dryRun,
        'revision' => 1,
    ], JSON_THROW_ON_ERROR);
}
