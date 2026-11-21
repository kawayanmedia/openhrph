<?php

namespace App\Jurisdictions\Contracts;

/**
 * Country remittance, tax and payslip reporting entry points.
 */
interface ReportProvider
{
    /**
     * @return array<string, string>
     */
    public function remittanceFormats(): array;

    /**
     * @return array<string, string>
     */
    public function commands(): array;

    public function payslipPdfView(): ?string;

    /**
     * @return array<string, string>
     */
    public function accountingAccounts(): array;
}
