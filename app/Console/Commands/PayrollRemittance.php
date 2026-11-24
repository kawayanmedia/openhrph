<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Services\Payroll\RemittanceReport;
use App\Support\Installation\Installation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Reconciles a payroll to the statutory remittances.
 *
 * The summary format keeps the original four-row reconciliation. The agency
 * formats emit employee-level working papers with a control total. GSIS loans
 * are not statutory contributions, so they are reported by the loans working
 * paper instead of the GSIS premium paper.
 */
class PayrollRemittance extends Command
{
    protected $signature = 'payroll:remittance
        {payroll : Payroll id}
        {--format=summary : summary|components|sss|philhealth|pagibig|gsis|bir|coa|loans|sector|all}
        {--path= : Write the CSV to this path, or a directory when --format=all}
        {--agency= : Loan agency filter for --format=loans, for example GSIS}
        {--no-control-totals : Omit the TOTAL row}';

    protected $description = 'Reconcile a payroll to the statutory remittances';

    public function handle(RemittanceReport $report): int
    {
        $payroll = Payroll::withoutTenant()->find($this->argument('payroll'));

        if (! $payroll) {
            $this->error('Payroll not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set((int) $payroll->company_id);

        if (! $payroll->payrollDetails()->exists()) {
            $this->error('This payroll has no details to reconcile.');

            return self::FAILURE;
        }

        $format = strtolower((string) $this->option('format'));
        $allowed = array_keys($report->formats());

        if ($format !== 'all' && ! in_array($format, $allowed, true)) {
            $this->error('Unsupported remittance format for '.Installation::countryCode().': '.$format);
            $this->line('Available: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        if ($format === 'all') {
            $formats = Installation::statutoryDriver() === 'ph'
                ? ['summary', 'components', 'sss', 'philhealth', 'pagibig', 'gsis', 'bir', 'coa', 'loans']
                : ['summary', 'components'];

            return $this->writeFormats($report, $payroll, $formats);
        }

        if ($format === 'sector') {
            return $this->writeFormats($report, $payroll, $report->sectorFormats($payroll));
        }

        if (! in_array($format, ['summary', 'components', 'sss', 'philhealth', 'pagibig', 'gsis', 'bir', 'coa', 'loans'], true)) {
            $this->error('Unknown format. Use summary, components, sss, philhealth, pagibig, gsis, bir, coa, loans, sector or all.');

            return self::FAILURE;
        }

        $csv = $report->csv($payroll, $format, [
            'agency' => $this->option('agency'),
            'control_totals' => ! $this->option('no-control-totals'),
        ]);

        return $this->write($csv, $this->option('path'), 'remittance-'.$format.'.csv');
    }

    /**
     * @param  array<int, string>  $formats
     */
    protected function writeFormats(RemittanceReport $report, Payroll $payroll, array $formats): int
    {
        $path = $this->option('path');

        if ($path && ! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        foreach ($formats as $format) {
            $csv = $report->csv($payroll, $format, [
                'agency' => $this->option('agency'),
                'control_totals' => ! $this->option('no-control-totals'),
            ]);

            if ($path) {
                file_put_contents(rtrim($path, '/').'/remittance-'.$format.'.csv', $csv);
                $this->line('Wrote remittance-'.$format.'.csv');
            } else {
                $this->line('--- '.strtoupper($format).' ---');
                $this->line($csv);
            }
        }

        return self::SUCCESS;
    }

    protected function write(string $csv, ?string $path, string $defaultName): int
    {
        if ($path) {
            if (is_dir($path)) {
                $path = rtrim($path, '/').'/'.$defaultName;
            }

            file_put_contents($path, $csv);
            $this->info('Remittance reconciliation written to '.$path);

            return self::SUCCESS;
        }

        $this->line($csv);

        return self::SUCCESS;
    }
}
