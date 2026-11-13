<?php

namespace App\Services\Payroll;

use App\Services\Payroll\Computation\Computer;
use App\Services\Payroll\Computation\Money;
use App\Services\Payroll\Computation\Payslip;
use App\Services\Payroll\Engine\Legacy\LegacyInput;
use App\Services\Payroll\Engine\Legacy\LegacyWriter;
use App\Services\Payroll\Engine\Legacy\Summary;
use App\Services\Payroll\Engine\Legacy\WriteOptions;
use App\Services\Payroll\Engine\PayrollEngineException;

/**
 * PHP driver for the payroll engine.
 *
 * The rules live in App\Services\Payroll\Computation, which is pinned to the Go
 * engine by the parity corpus in payroll-engine/internal/payroll/testdata/parity,
 * and the rows are written with the same column mapping the Go writer uses.
 * This class is deliberately only the loader/compute/store orchestration: it is
 * not a second implementation of the payroll rules.
 */
final class ProcessPayrollEngine
{
    public const ENGINE_VERSION = 'php-legacy';

    /**
     * Compute one payroll without persisting anything.
     *
     * @return array<string, mixed> the payload the Go CLI prints
     */
    public function preview(int $payrollId): array
    {
        $started = microtime(true);

        $input = LegacyInput::load($payrollId);
        $slips = Computer::computeAll($input->config, $input->employees);

        return $this->payload($input, $slips, true, 0, $this->elapsedMs($started));
    }

    /**
     * Compute and persist one payroll.
     */
    public function handlePayroll(?int $payrollId = null): Summary
    {
        if ($payrollId === null) {
            throw new PayrollEngineException('A payroll id is required to process a payroll.');
        }

        $input = LegacyInput::load($payrollId);
        $slips = Computer::computeAll($input->config, $input->employees);

        return LegacyWriter::write($input->run, $slips, new WriteOptions(
            engineVersion: self::ENGINE_VERSION,
            inputHash: $this->inputHash($input),
        ));
    }

    /**
     * The payload shape printed by the Go CLI.
     *
     * @param  list<Payslip>  $slips
     * @return array<string, mixed>
     */
    public function payload(LegacyInput $input, array $slips, bool $dryRun, int $revision, int $durationMs): array
    {
        $payslips = [];
        $warnings = [];
        $gross = 0;
        $deductions = 0;
        $net = 0;

        foreach ($slips as $slip) {
            $payslips[] = [
                'employee_id' => $slip->employeeID,
                'employee_number' => $slip->employeeNo,
                'name' => $slip->employeeName,
                'gross' => Money::format($slip->gross),
                'taxable_income' => Money::format($slip->taxableIncome),
                'withholding_tax' => Money::format($slip->withholdingTax),
                'sss_employee' => Money::format($slip->sssEmployee + $slip->sssProvident),
                'philhealth_employee' => Money::format($slip->philHealthEmployee),
                'pagibig_employee' => Money::format($slip->pagIbigEmployee),
                'total_deductions' => Money::format($slip->totalDeductions),
                'net_pay' => Money::format($slip->netPay),
                'employer_cost' => Money::format($slip->totalEmployerCost),
                'warnings' => $slip->warnings,
            ];

            $gross = Money::add($gross, $slip->gross);
            $deductions = Money::add($deductions, $slip->totalDeductions);
            $net = Money::add($net, $slip->netPay);
            $warnings = array_merge($warnings, $slip->warnings);
        }

        return [
            'engine_version' => self::ENGINE_VERSION,
            'payroll_id' => $input->run->payrollID,
            'dry_run' => $dryRun,
            'employees' => count($slips),
            'gross' => Money::format($gross),
            'deductions' => Money::format($deductions),
            'net' => Money::format($net),
            'duration_ms' => $durationMs,
            'revision' => $revision,
            'warnings' => $warnings,
            'payslips' => $payslips,
        ];
    }

    /**
     * Fingerprint the inputs so a re-processing can be compared with the run it
     * replaces. The digest is scoped to the engine that produced it and is
     * recorded alongside engine_version.
     */
    public function inputHash(LegacyInput $input): string
    {
        $payload = json_encode([
            'payroll_id' => $input->run->payrollID,
            'company_id' => $input->run->companyID,
            'group_id' => $input->run->groupID,
            'type' => $input->run->type,
            'date_from' => $input->run->dateFrom,
            'date_to' => $input->run->dateTo,
            'employees' => array_map(
                static fn ($employee): array => $employee->toArray(),
                $input->employees,
            ),
        ]);

        return hash('sha256', $payload === false ? '' : $payload);
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
