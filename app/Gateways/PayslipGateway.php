<?php

namespace App\Gateways;

use App\Models\PayrollDetail;
use App\Models\PayrollYearDate;
use setasign\Fpdi\Fpdi;

class PayslipGateway
{
    public $payrollDetail;

    public $payrollYearDate;

    public function get($id)
    {
        $this->payrollDetail = PayrollDetail::find($id);

        return [
            'payroll_detail' => $this->payrollDetail,
            'payroll_year_date' => PayrollYearDate::query()
                ->where('employee_id', $this->payrollDetail?->employee_id)
                ->where('year', $this->payrollDetail?->year)
                ->first(),
        ];
    }

    public function generatePdf()
    {
        $pdf = new Fpdi;

        $pdf->addPage();

        // $pdf->setSourceFile('soa template.pdf');
        $pdf->setSourceFile('docs/payslip.pdf');
        // $pdf->setSourceFile('docs/payslip_borders.pdf');
        // import page 1
        $tplId = $pdf->importPage(1);
        // use the imported page and place it at point 10,10 with a width of 100 mm
        $pdf->useTemplate($tplId);

        $pdf->SetFont('Arial', '', 10);
        $pdf->setXY(20, 21);
        $pdf->Write(5, get_setting('company_name'));

        if ((bool) get_setting('display_logo_payslip') == true) {
            $pdf->setXY(20, 10);
            if (get_setting('payslip_logo')) {
                $pdf->Image(storage_path().'/app/public/'.get_setting('payslip_logo'), 20, 10, -300);
            }
        }

        $pdf->setXY(70, 28);
        $pdf->Write(5, (string) ($this->payrollDetail->employee?->fullname ?? ''));

        $pdf->setXY(70, 34.5);
        $pdf->Write(5, (string) ($this->payrollDetail->payroll?->payroll_date?->format('d F Y') ?? ''));

        $pdf->setXY(70, 41);
        $pdf->Write(5, (string) ($this->payrollDetail->payroll?->covered_dates ?? ''));

        $pdf->setXY(70, 48);
        $pdf->Write(5, (string) ($this->payrollDetail->employee?->department?->name ?? ''));

        $pdf->setXY(70, 54);
        $pdf->Write(5, (string) ($this->payrollDetail->employee?->position ?? ''));

        // Right side tax status
        $pdf->setXY(160, 28);
        $pdf->Write(5, '__');

        $pdf->setXY(160, 34);
        $pdf->Write(5, (string) ($this->payrollDetail->employee?->tin_number ?? ''));

        $pdf->setXY(160, 41);
        $pdf->Write(5, (string) ($this->payrollDetail->employee?->sss_number ?? ''));

        $pdf->setXY(160, 48);
        $pdf->Write(5, (string) ($this->payrollDetail->employee?->philhealth_number ?? ''));

        $pdf->setXY(160, 54);
        $pdf->Write(5, $this->payrollDetail->employee->hdmf_number);

        // Compensation
        $pdf->setXY(58, 74);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->basic_salary, 2), 0, 'R');

        $pdf->setXY(58, 81);
        $pdf->MultiCell(20, 5, 0, 0, 'R');

        $pdf->setXY(58, 142);
        // $pdf->MultiCell(20, 5, number_format($this->payrollDetail->gross_income, 2), 0, 'R');
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->gross_income + $this->payrollDetail->allowance + $this->payrollDetail->incentive, 2), 0, 'R');

        // dd($this->payrollDetail->compensations);
        $y = 87;
        if ($this->payrollDetail->compensations != null) {

            $pdf->SetFont('Arial', '', 8);
            foreach ($this->payrollDetail->compensations as $compensation) {
                $pdf->setXY(20, $y);
                $pdf->MultiCell(30, 5, $compensation['name'], 0, 'L');

                $pdf->setXY(58, $y);
                $pdf->MultiCell(20, 5, number_format($compensation['amount'], 2), 0, 'R');

                $y += 12;
            }
            //
        }

        if ($this->payrollDetail->overtime_pay != 0) {
            $y += 6;
            $pdf->setXY(20, $y);
            $pdf->MultiCell(30, 5, 'OT', 0, 'L');

            $pdf->setXY(58, $y);
            $pdf->MultiCell(20, 5, number_format($this->payrollDetail->overtime_pay, 2), 0, 'R');
        }

        if ($this->payrollDetail->night_pay != 0) {
            $y += 6;
            $pdf->setXY(20, $y);
            $pdf->MultiCell(30, 5, 'ND', 0, 'L');

            $pdf->setXY(58, $y);
            $pdf->MultiCell(20, 5, number_format($this->payrollDetail->night_pay, 2), 0, 'R');
        }

        if ($this->payrollDetail->rest_day_pay != 0) {
            $y += 6;
            $pdf->setXY(20, $y);
            $pdf->MultiCell(30, 5, 'RD', 0, 'L');

            $pdf->setXY(58, $y);
            $pdf->MultiCell(20, 5, number_format($this->payrollDetail->rest_day_pay, 2), 0, 'R');
        }
        // $pdf->setXY(20, 87);
        // $pdf->MultiCell(30, 5, 'Overtime Pay', 0, 'L');

        // $pdf->setXY(58, 87);
        // $pdf->MultiCell(20, 5, $this->payrollDetail->overtime_pay, 0, 'R');

        // Deductions
        $pdf->setXY(118, 75);
        $pdf->MultiCell(20, 5, number_format(0, 2), 0, 'R');

        $pdf->setXY(118, 81);
        $pdf->MultiCell(20, 5, number_format(0, 2), 0, 'R');

        $pdf->setXY(118, 87);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->absent_days_amount, 2), 0, 'R');

        $pdf->setXY(118, 94);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->representation_allowance, 2), 0, 'R');

        $pdf->setXY(118, 100);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->sss_employee, 2), 0, 'R');

        $pdf->setXY(118, 106);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->sss_provident_employee, 2), 0, 'R');

        $pdf->setXY(118, 113);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->philhealth_employee, 2), 0, 'R');

        $pdf->setXY(118, 119);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->hdmf_employee, 2), 0, 'R');

        $pdf->setXY(118, 126);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->withholding_tax, 2), 0, 'R');

        $pdf->setXY(118, 142);
        $pdf->MultiCell(20, 5, number_format($this->payrollDetail->total_deductions, 2), 0, 'R');

        // Year to date
        $yearDate = PayrollYearDate::query()
            ->where('employee_id', $this->payrollDetail->employee_id)
            ->where('year', $this->payrollDetail->year)
            ->first();

        if ($yearDate) {
            $pdf->setXY(174, 74);
            $pdf->MultiCell(20, 5, number_format($yearDate->taxable_gross, 2), 0, 'R');

            $pdf->setXY(174, 81);
            $pdf->MultiCell(20, 5, number_format($yearDate->tax, 2), 0, 'R');

            $pdf->setXY(174, 87);
            $pdf->MultiCell(20, 5, number_format($yearDate->sss, 2), 0, 'R');

            $pdf->setXY(174, 93);
            $pdf->MultiCell(20, 5, number_format($yearDate->philhealth, 2), 0, 'R');

            $pdf->setXY(174, 99);
            $pdf->MultiCell(20, 5, number_format($yearDate->hdmf, 2), 0, 'R');

            $pdf->setXY(174, 106);
            $pdf->MultiCell(20, 5, number_format($yearDate->gross_income, 2), 0, 'R');

            $pdf->setXY(174, 113);
            $pdf->MultiCell(20, 5, number_format($yearDate->non_taxable, 2), 0, 'R');
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->setXY(174, 142);
        $pdf->MultiCell(22, 5, number_format($this->payrollDetail->net_pay, 2), 0, 'R');

        $pdf->Output('I', 'generated.pdf');
    }
}
