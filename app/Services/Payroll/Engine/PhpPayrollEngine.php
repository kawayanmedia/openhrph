<?php

namespace App\Services\Payroll\Engine;

use App\Models\Payroll;
use App\Services\Payroll\ProcessPayrollEngine;

/**
 * PHP payroll driver, used when the Go binary is not available.
 *
 * The rules are the parity-locked port in App\Services\Payroll\Computation, so
 * this driver returns the same payslips as the Go engine for the same rows.
 */
class PhpPayrollEngine implements PayrollEngine
{
    public function __construct(
        protected ?ProcessPayrollEngine $engine = null,
    ) {
        $this->engine ??= new ProcessPayrollEngine;
    }

    public function preview(Payroll $payroll): EngineResult
    {
        $started = microtime(true);

        $payload = $this->engine->preview((int) $payroll->getKey());
        $payload['duration_ms'] = (int) round((microtime(true) - $started) * 1000);

        return EngineResult::fromArray($payload);
    }

    public function process(Payroll $payroll, bool $force = false): EngineResult
    {
        $started = microtime(true);

        $summary = $this->engine->handlePayroll((int) $payroll->getKey());

        return new EngineResult(
            payrollId: $summary->payrollID,
            employees: $summary->employeeCount,
            gross: $summary->grossPesos(),
            deductions: $summary->deductionsPesos(),
            net: $summary->netPesos(),
            warnings: $summary->warnings,
            durationMs: (int) round((microtime(true) - $started) * 1000),
            engineVersion: ProcessPayrollEngine::ENGINE_VERSION,
            revision: $summary->revision,
        );
    }

    public function version(): ?string
    {
        return ProcessPayrollEngine::ENGINE_VERSION;
    }
}
