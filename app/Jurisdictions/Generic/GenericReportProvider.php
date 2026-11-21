<?php

namespace App\Jurisdictions\Generic;

use App\Jurisdictions\Contracts\ReportProvider;

final class GenericReportProvider implements ReportProvider
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(private readonly array $profile = []) {}

    public function remittanceFormats(): array
    {
        return (array) ($this->profile['reports']['remittance'] ?? [
            'summary' => 'Summary',
            'components' => 'Components',
        ]);
    }

    public function commands(): array
    {
        return (array) ($this->profile['reports']['commands'] ?? []);
    }

    public function payslipPdfView(): ?string
    {
        $view = $this->profile['reports']['payslip_pdf_view'] ?? null;

        return is_string($view) && $view !== '' ? $view : null;
    }

    public function accountingAccounts(): array
    {
        return (array) ($this->profile['accounting']['accounts'] ?? config('payroll.accounting.accounts', []));
    }
}
