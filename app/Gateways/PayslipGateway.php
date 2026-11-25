<?php

namespace App\Gateways;

use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;
use App\Jurisdictions\Philippines\Statutory\Schemes\SchemeResolver;
use App\Jurisdictions\Philippines\Statutory\Schemes\SocialInsuranceScheme;
use App\Sectors\SectorManager;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollYearDate;
use App\Support\Installation\Installation;
use FPDF;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renders the payable payslip.
 *
 * The form is drawn in code instead of importing a fixed PDF template, so a
 * government payroll can label the statutory deduction GSIS and print the
 * GSIS BP number without leaving an SSS caption underneath it.
 */
class PayslipGateway
{
    public ?PayrollDetail $payrollDetail = null;

    public ?PayrollYearDate $payrollYearDate = null;

    /**
     * @return array<string, mixed>
     */
    public function get($id): array
    {
        $this->payrollDetail = PayrollDetail::query()
            ->with(['employee.department', 'payroll.processedBy', 'payroll.approvedBy', 'deductionLines'])
            ->find($id);

        $this->payrollYearDate = $this->payrollDetail?->employee_id === null
            ? null
            : PayrollYearDate::query()
                ->where('employee_id', $this->payrollDetail->employee_id)
                ->where('year', $this->payrollDetail->year)
                ->first();

        return [
            'payroll_detail' => $this->payrollDetail,
            'payroll_year_date' => $this->payrollYearDate,
        ];
    }

    /**
     * The inline PDF response for the payslip route.
     */
    public function generatePdf(): Response
    {
        return response($this->pdfBytes(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->fileName().'"',
        ]);
    }

    /**
     * The rendered PDF, for callers that need the bytes (evidence packs).
     */
    public function pdfBytes(): string
    {
        return (string) $this->pdf()->Output('S', $this->fileName());
    }

    protected function fileName(): string
    {
        $year = (int) ($this->payrollDetail?->year ?? now()->year);

        return 'payslip-'.($this->payrollDetail?->employee_id ?? 0).'-'.$year.'.pdf';
    }

    protected function pdf(): FPDF
    {
        $detail = $this->payrollDetail;

        abort_if($detail === null, 404);

        $employee = $detail->employee;
        $payroll = $detail->payroll;
        $scheme = SchemeResolver::make(
            app(SectorManager::class)->forCompany((int) $detail->company_id)->socialInsuranceAgency(),
            [],
            GsisSchedule::default(),
        );

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $this->header($pdf, $employee, $payroll);
        $this->identityBlock($pdf, $employee, $scheme);

        $this->sectionTitle($pdf, 'EARNINGS');
        $this->rows($pdf, $this->earningsRows($detail));
        $this->totalRow($pdf, 'Gross pay', (float) $detail->gross_income);

        $this->sectionTitle($pdf, 'DEDUCTIONS');
        $this->rows($pdf, $this->deductionRows($detail, $scheme));
        $this->totalRow($pdf, 'Total deductions', (float) $detail->total_deductions);

        $this->netPay($pdf, (float) $detail->net_pay);

        $this->sectionTitle($pdf, 'EMPLOYER AND YEAR TO DATE');
        $this->rows($pdf, array_merge(
            [
                ['Employer share', (float) $detail->total_employer],
                ['Taxable income', (float) $detail->taxable_income],
            ],
            $this->yearToDateRows($detail),
        ));

        $this->footer($pdf, $payroll);

        return $pdf;
    }

    /**
     * @param  Employee|null  $employee
     * @param  Payroll|null  $payroll
     */
    protected function header(FPDF $pdf, $employee, $payroll): void
    {
        $company = (string) (get_setting('company_name') ?: config('app.name'));
        $hasLogo = false;

        if ((bool) get_setting('display_logo_payslip') && get_setting('payslip_logo')) {
            $logo = storage_path('app/public/'.get_setting('payslip_logo'));
            $hasLogo = is_file($logo);

            if ($hasLogo) {
                $pdf->Image($logo, 15, 12, 22);
            }
        }

        $leftWidth = $hasLogo ? 98 : 125;
        $pdf->SetXY($hasLogo ? 42 : 15, 14);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell($leftWidth, 7, $this->text($company), 0, 0, 'L');
        $pdf->Cell(55, 7, 'PAYSLIP', 0, 1, 'R');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(110);
        $pdf->Cell($leftWidth, 5, 'Amounts in '.Installation::currencyCode(), 0, 0, 'L');
        $pdf->Cell(55, 5, 'Pay date '.$this->date($payroll?->payroll_date), 0, 1, 'R');
        $pdf->Cell($leftWidth + 55, 5, 'Period '.$this->date($payroll?->date_from).' - '.$this->date($payroll?->date_to), 0, 1, 'R');
        $pdf->SetTextColor(0);

        $pdf->SetDrawColor(200);
        $pdf->Line(15, $pdf->GetY() + 1, 195, $pdf->GetY() + 1);
        $pdf->Ln(4);
    }

    protected function identityBlock(FPDF $pdf, $employee, SocialInsuranceScheme $scheme): void
    {
        $left = [
            ['Employee', trim((string) ($employee?->first_name ?? '').' '.(string) ($employee?->last_name ?? ''))],
            ['Employee no', (string) ($employee?->employee_number ?? '')],
            ['Position', (string) ($employee?->position ?? '')],
            ['Department', (string) ($employee?->department?->name ?? '')],
        ];

        $right = [
            ['TIN', $this->mask($employee?->tin_number)],
            [$scheme->identifierLabel(), $this->mask($employee?->{$scheme->identifierField()})],
            ['PhilHealth', $this->mask($employee?->philhealth_number)],
            ['Pag-IBIG', $this->mask($employee?->hdmf_number)],
        ];

        $top = $pdf->GetY();

        foreach ($left as $index => [$label, $value]) {
            $this->identityRow($pdf, 15, $top + ($index * 5.5), $label, $value);
        }

        foreach ($right as $index => [$label, $value]) {
            $this->identityRow($pdf, 110, $top + ($index * 5.5), $label, $value);
        }

        $pdf->SetY($top + (max(count($left), count($right)) * 5.5) + 2);
    }

    protected function identityRow(FPDF $pdf, float $x, float $y, string $label, string $value): void
    {
        $pdf->SetXY($x, $y);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(110);
        $pdf->Cell(28, 5, $this->text($label), 0, 0, 'L');
        $pdf->SetTextColor(0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(55, 5, $this->text($value !== '' ? $value : '-'), 0, 0, 'L');
    }

    protected function sectionTitle(FPDF $pdf, string $title): void
    {
        $pdf->Ln(3);
        $pdf->SetFillColor(238, 242, 246);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(180, 6, '  '.$title, 0, 1, 'L', true);
        $pdf->Ln(1);
    }

    /**
     * @param  list<array{0: string, 1: float}>  $rows
     */
    protected function rows(FPDF $pdf, array $rows): void
    {
        $rows = array_values(array_filter($rows, static fn (array $row): bool => abs($row[1]) >= 0.005));

        if ($rows === []) {
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(120);
            $pdf->Cell(180, 5.5, 'None', 0, 1, 'L');
            $pdf->SetTextColor(0);

            return;
        }

        foreach ($rows as [$label, $amount]) {
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(120, 5.5, $this->text($label), 0, 0, 'L');
            $pdf->Cell(60, 5.5, $this->money($amount), 0, 1, 'R');
        }
    }

    protected function totalRow(FPDF $pdf, string $label, float $amount): void
    {
        $pdf->SetDrawColor(200);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(120, 7, $this->text($label), 0, 0, 'L');
        $pdf->Cell(60, 7, $this->money($amount), 0, 1, 'R');
    }

    protected function netPay(FPDF $pdf, float $amount): void
    {
        $pdf->Ln(3);
        $pdf->SetFillColor(20, 38, 58);
        $pdf->SetTextColor(255);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(120, 11, '  NET PAY', 0, 0, 'L', true);
        $pdf->Cell(60, 11, $this->money($amount).'  ', 0, 1, 'R', true);
        $pdf->SetTextColor(0);
    }

    /**
     * @return list<array{0: string, 1: float}>
     */
    protected function earningsRows(PayrollDetail $detail): array
    {
        $rows = [
            ['Basic pay (period)', (float) $detail->basic_salary],
            ['Attendance and premiums', (float) $detail->regular_pay - (float) $detail->basic_salary],
            ['Overtime', (float) $detail->overtime_pay],
            ['Night differential', (float) $detail->night_pay],
            ['Rest day', (float) $detail->rest_day_pay],
            ['Holiday', (float) $detail->holiday_pay],
            ['Allowances', (float) $detail->allowance],
        ];

        foreach ($detail->compensation_lines as $line) {
            $rows[] = [(string) ($line['name'] ?? ''), (float) ($line['amount'] ?? 0)];
        }

        $rows = array_merge($rows, [
            ['Other incentives', (float) $detail->unitemised_incentive],
            ['Bonus', (float) $detail->bonus],
            ['Commission', (float) $detail->commission],
            ['13th month', (float) $detail->thirteenth_month],
            ['Leave conversion', (float) $detail->leave_conversion],
            ['Separation pay', (float) $detail->separation_pay],
        ]);

        if ((float) $detail->absent_days_amount > 0) {
            $rows[] = ['Absences', -1 * (float) $detail->absent_days_amount];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: float}>
     */
    protected function deductionRows(PayrollDetail $detail, SocialInsuranceScheme $scheme): array
    {
        $lines = $detail->deductionLines;

        if ($lines->isEmpty()) {
            return $this->legacyDeductionRows($detail, $scheme);
        }

        $rows = [];

        foreach ($lines as $line) {
            $amount = ((int) $line->employee_centavos) / 100;

            if (abs($amount) < 0.005) {
                continue;
            }

            $rows[] = [(string) ($line->name !== '' ? $line->name : $line->code), (float) $amount];
        }

        return $rows;
    }

    /**
     * Payroll details written before the deduction ledger fall back to the
     * dedicated columns. A government agency has no SSS deduction.
     *
     * @return list<array{0: string, 1: float}>
     */
    protected function legacyDeductionRows(PayrollDetail $detail, SocialInsuranceScheme $scheme): array
    {
        $rows = [];

        $rows[] = $scheme->code() === 'gsis'
            ? ['GSIS', (float) $detail->gsis_employee]
            : ['SSS', (float) $detail->sss_employee + (float) $detail->sss_provident_employee];

        $rows[] = ['PhilHealth', (float) $detail->philhealth_employee];
        $rows[] = ['Pag-IBIG', (float) $detail->hdmf_employee];
        $rows[] = ['Withholding tax', (float) $detail->withholding_tax];
        $rows[] = ['Loans', (float) $detail->loans];
        $rows[] = [
            'Other deductions',
            max(0.0, (float) $detail->total_deductions - (float) $detail->total_employee - (float) $detail->withholding_tax),
        ];

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: float}>
     */
    protected function yearToDateRows(PayrollDetail $detail): array
    {
        if (Schema::hasTable('employee_payroll_ytd')) {
            $row = DB::table('employee_payroll_ytd')
                ->where('company_id', $detail->company_id)
                ->where('employee_id', $detail->employee_id)
                ->where('year', $detail->year)
                ->first();

            if ($row !== null) {
                return [
                    ['YTD gross', ((int) ($row->gross_earned ?? 0)) / 100],
                    ['YTD taxable income', ((int) ($row->taxable_income ?? 0)) / 100],
                    ['YTD tax withheld', ((int) ($row->tax_withheld ?? 0)) / 100],
                    ['YTD contributions', ((int) ($row->contributions ?? 0)) / 100],
                ];
            }
        }

        $ytd = $this->payrollYearDate;

        if ($ytd === null) {
            return [];
        }

        return [
            ['YTD gross', (float) $ytd->gross_income],
            ['YTD taxable income', (float) $ytd->taxable_gross],
            ['YTD tax withheld', (float) $ytd->tax],
            ['YTD contributions', (float) $ytd->sss + (float) $ytd->philhealth + (float) $ytd->hdmf],
        ];
    }

    /**
     * @param  Payroll|null  $payroll
     */
    protected function footer(FPDF $pdf, $payroll): void
    {
        $pdf->Ln(5);
        $pdf->SetDrawColor(200);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(110);

        $pdf->Cell(
            180,
            5,
            $this->text('Prepared by: '.($payroll?->processedBy?->name ?? '-').'   Approved by: '.($payroll?->approvedBy?->name ?? '-')),
            0,
            1,
            'L',
        );

        $pdf->Cell(180, 5, 'Payroll #'.($payroll?->getKey() ?? '-').'   Generated '.now()->format('d M Y H:i'), 0, 1, 'L');
        $pdf->SetTextColor(0);
    }

    protected function money(float $amount): string
    {
        return number_format($amount, 2);
    }

    /**
     * @param  mixed  $value
     */
    protected function date($value): string
    {
        if ($value === null) {
            return '-';
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('d M Y')
            : (string) $value;
    }

    protected function mask(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $length = strlen($value);

        return $length <= 4
            ? str_repeat('*', $length)
            : str_repeat('*', $length - 4).substr($value, -4);
    }

    /**
     * Core FPDF fonts are single-byte, so keep the form inside cp1252.
     */
    protected function text(string $value): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $value);

        return $converted === false ? $value : $converted;
    }
}
