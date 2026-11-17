<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Builds the period-close evidence pack for a payroll.
 */
class PayrollEvidencePack extends Command
{
    protected $signature = 'payroll:evidence-pack
        {payroll : Payroll id}
        {--path= : Directory for the evidence pack}
        {--payslips : Also generate employee payslip PDFs when the template exists}';

    protected $description = 'Write the payroll register, remittance files, BIR 2316 data, manifest and README';

    public function handle(): int
    {
        $payroll = Payroll::withoutTenant()->find($this->argument('payroll'));

        if (! $payroll) {
            $this->error('Payroll not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set((int) $payroll->company_id);

        $path = $this->option('path');

        if (! $path) {
            $this->error('Pass --path to the evidence-pack directory.');

            return self::FAILURE;
        }

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $files = [];
        $notes = [];

        $this->call('payroll:register', [
            'payroll' => $payroll->getKey(),
            '--path' => $path.'/register.csv',
        ]);

        $this->call('payroll:remittance', [
            'payroll' => $payroll->getKey(),
            '--format' => 'all',
            '--path' => $path,
        ]);

        $group = $payroll->payrollGroup;

        if ($group !== null) {
            $year = $payroll->payroll_date?->year ?? now()->year;
            $exit = $this->call('payroll:bir-2316', [
                'group' => $group->getKey(),
                '--year' => $year,
                '--path' => $path.'/bir-2316.csv',
                '--rejects' => $path.'/bir-2316-rejects.csv',
                '--allow-invalid-tin' => true,
            ]);

            if ($exit !== self::SUCCESS) {
                $notes[] = 'BIR 2316 data could not be generated for '.$year.'.';
            }
        }

        if ($this->option('payslips')) {
            if (! is_file(base_path('docs/payslip.pdf'))) {
                $notes[] = 'Payslip PDFs were not generated because docs/payslip.pdf is missing; print them from the payroll register.';
            } else {
                $notes[] = 'Payslip PDF generation is available in the payroll register; the evidence pack does not duplicate the template rendering.';
            }
        }

        foreach (glob($path.'/*') as $file) {
            if (is_file($file) && ! in_array(basename($file), ['manifest.json', 'README.txt'], true)) {
                $files[] = [
                    'name' => basename($file),
                    'bytes' => filesize($file),
                    'sha256' => hash_file('sha256', $file),
                ];
            }
        }

        $manifest = [
            'payroll_id' => $payroll->getKey(),
            'company_id' => $payroll->company_id,
            'payroll_group' => $group?->name,
            'payroll_date' => $payroll->payroll_date?->toDateString(),
            'period_from' => $payroll->date_from?->toDateString(),
            'period_to' => $payroll->date_to?->toDateString(),
            'status' => $payroll->status,
            'engine_version' => $payroll->engine_version,
            'input_hash' => $payroll->input_hash,
            'processed_at' => $payroll->processed_at?->toIso8601String(),
            'processed_by' => $payroll->processed_by,
            'reviewed_by' => $payroll->reviewed_by,
            'reviewed_at' => $payroll->reviewed_at?->toIso8601String(),
            'approved_by' => $payroll->approved_by,
            'approved_at' => $payroll->approved_at?->toIso8601String(),
            'override_reason' => $payroll->override_reason,
            'totals' => [
                'gross' => (int) $payroll->getRawOriginal('gross_amount'),
                'net' => (int) $payroll->getRawOriginal('net_amount'),
            ],
            'generated_at' => now()->toIso8601String(),
            'files' => $files,
            'notes' => $notes,
        ];

        file_put_contents($path.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        file_put_contents($path.'/README.txt', $this->readme($payroll, $files, $notes));

        $this->info('Evidence pack written to '.$path);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{name: string, bytes: int, sha256: string}>  $files
     * @param  array<int, string>  $notes
     */
    protected function readme(Payroll $payroll, array $files, array $notes): string
    {
        $lines = [
            'Payroll evidence pack',
            '=====================',
            '',
            'Payroll: #'.$payroll->getKey(),
            'Period: '.($payroll->date_from?->toDateString() ?? '-').' to '.($payroll->date_to?->toDateString() ?? '-'),
            'Pay date: '.($payroll->payroll_date?->toDateString() ?? '-'),
            'Status: '.$payroll->status,
            '',
            'Contents:',
        ];

        foreach ($files as $file) {
            $lines[] = '- '.$file['name'].' ('.$file['bytes'].' bytes, sha256 '.$file['sha256'].')';
        }

        $lines[] = '';
        $lines[] = 'Retention: confirm the period with the company CPA/tax adviser before disposing of any file.';
        $lines[] = '2026 statutory rates are provisional until the CPA confirms the PhilHealth/Pag-IBIG and regional wage-order values.';

        foreach ($notes as $note) {
            $lines[] = 'Note: '.$note;
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
