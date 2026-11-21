<?php

namespace App\Jurisdictions\Philippines\Reports;

use App\Jurisdictions\Contracts\ReportProvider;

final class PhilippinesReportProvider implements ReportProvider
{
    public function remittanceFormats(): array
    {
        return [
            'summary' => 'Summary',
            'components' => 'Components',
            'sss' => 'SSS',
            'philhealth' => 'PhilHealth',
            'pagibig' => 'Pag-IBIG',
            'gsis' => 'GSIS',
            'bir' => 'BIR',
            'coa' => 'COA register',
            'loans' => 'Loans',
            'sector' => 'Sector remittance set',
            'all' => 'All formats',
        ];
    }

    public function commands(): array
    {
        return [
            'payroll:bir-2316' => 'BIR 2316 year-to-date export',
            'payroll:step-increments' => 'Government step increments',
            'payroll:loyalty-awards' => 'Government loyalty awards',
            'payroll:terminal-leave' => 'Government terminal leave',
            'payroll:wage-order-import' => 'Philippine wage order import',
        ];
    }

    public function payslipPdfView(): ?string
    {
        return 'payroll.payslip.pdf';
    }

    public function accountingAccounts(): array
    {
        return (array) config('payroll.accounting.accounts', []);
    }
}
