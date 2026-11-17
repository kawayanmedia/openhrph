<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function governmentPayslipFixture(): PayrollDetail
{
    return app(TenantContext::class)->run(1, function (): PayrollDetail {
        Company::factory()->create(['id' => 1, 'type' => 'Government']);

        PayrollGroup::factory()->create(['id' => 1, 'company_id' => 1]);

        $employee = Employee::factory()->create([
            'company_id' => 1,
            'payroll_group_id' => 1,
            'office_id' => null,
            'employee_number' => 'E-900',
            'first_name' => 'Gina',
            'last_name' => 'Cruz',
            'gsis_number' => '0100-0000-0001',
            'tin_number' => '123-456-789-000',
            'philhealth_number' => '12-345678901-2',
            'hdmf_number' => '1234-5678-9012',
        ]);

        $payroll = Payroll::factory()->create([
            'company_id' => 1,
            'payroll_group_id' => 1,
            'status' => Payroll::STATUS_PROCESSED,
            'cut_off' => 'last_pay',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-15',
            'payroll_date' => '2026-03-15',
            'type' => 'regular',
            'meta' => [],
        ]);

        return PayrollDetail::factory()->create([
            'company_id' => 1,
            'payroll_id' => $payroll->getKey(),
            'employee_id' => $employee->getKey(),
            'basic_monthly_salary' => 3000000,
            'basic_salary' => 1500000,
            'regular_pay' => 1500000,
            'gross_income' => 1500000,
            'gsis_employee' => 135000,
            'gsis_employer' => 180000,
            'gsis_total' => 315000,
            'philhealth_employee' => 37500,
            'hdmf_employee' => 10000,
            'total_employee' => 182500,
            'total_employer' => 227500,
            'total_deductions' => 182500,
            'net_pay' => 1317500,
            'taxable_income' => 1317500,
            'year' => 2026,
        ]);
    });
}

/**
 * Decode the Flate-compressed page streams so the drawn labels can be asserted.
 */
function payslipPdfText(string $pdf): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);

    $text = '';

    foreach ($matches[1] as $stream) {
        $decoded = @gzuncompress($stream);

        if ($decoded !== false) {
            $text .= $decoded."\n";
        }
    }

    return $text;
}

it('renders a government payslip PDF labelled GSIS rather than SSS', function () {
    $detail = governmentPayslipFixture();

    $user = User::factory()->create(['company_id' => 1, 'owner' => true]);
    $this->actingAs($user);

    $response = $this->get(route('payslip.show', ['id' => $detail->getKey()]));

    $response->assertOk();

    $body = (string) $response->getContent();
    $labels = payslipPdfText($body);

    expect($response->headers->get('content-type'))->toContain('pdf')
        ->and($body)->toStartWith('%PDF')
        ->and($labels)->toContain('GSIS BP')
        ->and($labels)->not->toContain('SSS');
});
