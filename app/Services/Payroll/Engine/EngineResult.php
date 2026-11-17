<?php

namespace App\Services\Payroll\Engine;

/**
 * Normalised result returned by a payroll engine run.
 */
final class EngineResult
{
    /**
     * @param  array<int, array<string, mixed>>  $payslips
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly int $payrollId,
        public readonly int $employees,
        public readonly string $gross,
        public readonly string $deductions,
        public readonly string $net,
        public readonly array $payslips = [],
        public readonly array $warnings = [],
        public readonly int $durationMs = 0,
        public readonly string $engineVersion = 'unknown',
        public readonly bool $dryRun = false,
        public readonly int $revision = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            payrollId: (int) ($payload['payroll_id'] ?? 0),
            employees: (int) ($payload['employees'] ?? 0),
            gross: (string) ($payload['gross'] ?? '0.00'),
            deductions: (string) ($payload['deductions'] ?? '0.00'),
            net: (string) ($payload['net'] ?? '0.00'),
            payslips: is_array($payload['payslips'] ?? null) ? $payload['payslips'] : [],
            warnings: is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [],
            durationMs: (int) ($payload['duration_ms'] ?? 0),
            engineVersion: (string) ($payload['engine_version'] ?? 'unknown'),
            dryRun: (bool) ($payload['dry_run'] ?? false),
            revision: (int) ($payload['revision'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payroll_id' => $this->payrollId,
            'employees' => $this->employees,
            'gross' => $this->gross,
            'deductions' => $this->deductions,
            'net' => $this->net,
            'payslips' => $this->payslips,
            'warnings' => $this->warnings,
            'duration_ms' => $this->durationMs,
            'engine_version' => $this->engineVersion,
            'dry_run' => $this->dryRun,
            'revision' => $this->revision,
        ];
    }
}
