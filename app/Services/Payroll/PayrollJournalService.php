<?php

namespace App\Services\Payroll;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posts the payroll run to the general ledger.
 *
 * Dr  Salaries and wages expense            Σ gross_income
 * Dr  Employer contributions expense        Σ total_employer
 *     Cr  Withholding tax payable                    Σ withholding_tax
 *     Cr  SSS contributions payable                  Σ sss_employee_total + sss_employer_total
 *     Cr  PhilHealth contributions payable           Σ total_philhealth
 *     Cr  Pag-IBIG contributions payable             Σ hdmf_total
 *     Cr  Loans and other deductions payable         Σ (total_deductions - statutory - tax)
 *     Cr  Net pay payable                            Σ net_pay
 */
class PayrollJournalService
{
    public function post(Payroll $payroll): ?JournalEntry
    {
        if (! Schema::hasTable('journal_entries') || ! Schema::hasTable('journal_entry_lines')) {
            return null;
        }

        $details = $payroll->payrollDetails()->get();

        if ($details->isEmpty()) {
            return null;
        }

        $totals = [
            'gross' => 0,
            'employer' => 0,
            'tax' => 0,
            'sss' => 0,
            'philhealth' => 0,
            'pagibig' => 0,
            'other' => 0,
            'net' => 0,
        ];

        foreach ($details as $detail) {
            $statutoryEmployee = $this->raw($detail, 'sss_employee_total')
                + $this->raw($detail, 'hdmf_employee')
                + $this->raw($detail, 'philhealth_employee');

            $totals['gross'] += $this->raw($detail, 'gross_income');
            $totals['employer'] += $this->raw($detail, 'total_employer');
            $totals['tax'] += $this->raw($detail, 'withholding_tax');
            $totals['sss'] += $this->raw($detail, 'sss_employee_total') + $this->raw($detail, 'sss_employer_total');
            $totals['philhealth'] += $this->raw($detail, 'total_philhealth');
            $totals['pagibig'] += $this->raw($detail, 'hdmf_total');
            $totals['other'] += max(0, $this->raw($detail, 'total_deductions') - $statutoryEmployee - $this->raw($detail, 'withholding_tax'));
            $totals['net'] += $this->raw($detail, 'net_pay');
        }

        $accounts = config('payroll.accounting.accounts');

        return DB::transaction(function () use ($payroll, $totals, $accounts) {
            $this->clear($payroll);

            $entry = JournalEntry::create([
                'company_id' => $payroll->company_id,
                'payroll_id' => $payroll->getKey(),
                'entry_no' => 'PR-'.$payroll->getKey(),
                'entry_date' => optional($payroll->payroll_date)->toDateString() ?? now()->toDateString(),
                'memo' => 'Payroll #'.$payroll->getKey(),
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $lines = [
                [$accounts['salaries_expense'], 'Salaries and wages expense', $totals['gross'], 0],
                [$accounts['employer_contributions_expense'], 'Employer contributions expense', $totals['employer'], 0],
                [$accounts['withholding_tax_payable'], 'Withholding tax payable', 0, $totals['tax']],
                [$accounts['sss_payable'], 'SSS contributions payable', 0, $totals['sss']],
                [$accounts['philhealth_payable'], 'PhilHealth contributions payable', 0, $totals['philhealth']],
                [$accounts['pagibig_payable'], 'Pag-IBIG contributions payable', 0, $totals['pagibig']],
                [$accounts['other_deductions_payable'], 'Loans and other deductions payable', 0, $totals['other']],
                [$accounts['net_pay_payable'], 'Net pay payable', 0, $totals['net']],
            ];

            foreach ($lines as [$code, $name, $debit, $credit]) {
                if ($debit === 0 && $credit === 0) {
                    continue;
                }

                JournalEntryLine::create([
                    'company_id' => $payroll->company_id,
                    'journal_entry_id' => $entry->getKey(),
                    'account_code' => $code,
                    'account_name' => $name,
                    'debit_centavos' => $debit,
                    'credit_centavos' => $credit,
                ]);
            }

            return $entry;
        });
    }

    public function reverse(Payroll $payroll, string $reason): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }

        $this->clear($payroll);

        PayrollAudit::record($payroll, 'journal_reversed', $reason);
    }

    /**
     * Read the stored centavos value, bypassing the model's peso accessors.
     */
    private function raw(PayrollDetail $detail, string $column): int
    {
        return (int) $detail->getRawOriginal($column);
    }

    private function clear(Payroll $payroll): void
    {
        $ids = JournalEntry::query()
            ->where('company_id', $payroll->company_id)
            ->where('payroll_id', $payroll->getKey())
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            JournalEntryLine::query()->whereIn('journal_entry_id', $ids)->delete();
            JournalEntry::query()->whereIn('id', $ids)->delete();
        }
    }
}
