<?php

namespace App\Services\Payroll\Engine\Parity;

use App\Services\Payroll\Computation\Computer;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\Employee;

/**
 * The shared contract between the Go engine and the PHP driver.
 *
 * Every file in payroll-engine/internal/payroll/testdata/parity is produced by
 * the Go engine and holds the scenario input plus the payslip it computed.
 * Replaying a fixture through the PHP port must reproduce it exactly.
 */
final class ParityCorpus
{
    public static function path(): string
    {
        return base_path('payroll-engine/internal/payroll/testdata/parity');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fixtures(): array
    {
        $paths = glob(self::path().'/*.json') ?: [];

        $fixtures = [];

        foreach ($paths as $path) {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            $fixtures[basename($path, '.json')] = $decoded;
        }

        return $fixtures;
    }

    /**
     * Replay every fixture through the PHP port.
     *
     * @return list<string> mismatch descriptions, empty when the engines agree
     */
    public static function verify(): array
    {
        $mismatches = [];

        foreach (self::fixtures() as $name => $fixture) {
            $config = Config::fromArray($fixture['config']);
            $employee = Employee::fromArray($fixture['employee']);

            $slip = Computer::compute($config, $employee);

            foreach (self::compare($fixture['expected'], $slip->toArray(), $name) as $mismatch) {
                $mismatches[] = $mismatch;
            }
        }

        return $mismatches;
    }

    /**
     * @return list<string>
     */
    public static function compare(mixed $expected, mixed $actual, string $path): array
    {
        if (is_array($expected)) {
            if (! is_array($actual)) {
                return ["{$path}: expected an array"];
            }

            if (array_keys($actual) !== array_keys($expected)) {
                return ["{$path}: key set drifted from the Go engine"];
            }

            $mismatches = [];

            foreach ($expected as $key => $value) {
                foreach (self::compare($value, $actual[$key], "{$path}.{$key}") as $mismatch) {
                    $mismatches[] = $mismatch;
                }
            }

            return $mismatches;
        }

        if ((is_int($expected) || is_float($expected)) && (is_int($actual) || is_float($actual))) {
            return abs((float) $expected - (float) $actual) < 0.000001
                ? []
                : ["{$path}: expected {$expected}, got {$actual}"];
        }

        return $actual === $expected
            ? []
            : ["{$path}: expected ".json_encode($expected).', got '.json_encode($actual)];
    }
}
