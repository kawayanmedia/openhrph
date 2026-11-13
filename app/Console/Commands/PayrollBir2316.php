<?php

namespace App\Console\Commands;

use App\Models\PayrollGroup;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exports the per-employee year-to-date figures used to prepare BIR 2316.
 */
class PayrollBir2316 extends Command
{
    protected $signature = 'payroll:bir-2316
        {group : Payroll group id}
        {--year= : Calendar year (defaults to the current year)}
        {--path= : Write the CSV to this path instead of stdout}
        {--rejects= : Write rejected rows to this path}
        {--allow-invalid-tin : Include rows with an invalid TIN and record the override}';

    protected $description = 'Export year-to-date payroll figures for BIR 2316';

    public function handle(): int
    {
        if (! Schema::hasTable('employee_payroll_ytd')) {
            $this->error('The employee_payroll_ytd table does not exist. Run the migrations first.');

            return self::FAILURE;
        }

        $group = PayrollGroup::withoutTenant()->find($this->argument('group'));

        if (! $group) {
            $this->error('Payroll group not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set((int) $group->company_id);

        $year = (int) ($this->option('year') ?: now()->year);

        $records = DB::table('employee_payroll_ytd as ytd')
            ->join('employees as e', 'e.id', '=', 'ytd.employee_id')
            ->where('ytd.company_id', $group->company_id)
            ->where('ytd.year', $year)
            ->where('e.payroll_group_id', $group->getKey())
            ->whereNull('e.deleted_at')
            ->orderBy('e.employee_number')
            ->get([
                'e.employee_number',
                'e.first_name',
                'e.last_name',
                'e.tin_number',
                'ytd.gross_earned',
                'ytd.taxable_income',
                'ytd.tax_withheld',
                'ytd.thirteenth_month_paid',
                'ytd.other_benefits',
                'ytd.contributions',
            ]);

        if ($records->isEmpty()) {
            $this->error('No year-to-date records exist for '.$year.'.');

            return self::FAILURE;
        }

        $rows = [['Employee no', 'Name', 'TIN', 'Gross', 'Taxable', 'Tax withheld', '13th month', 'Other benefits', 'Contributions']];
        $rejects = [];
        $tinPattern = '/^\d{3}-?\d{3}-?\d{3}(-?\d{3,5})?$/';

        foreach ($records as $record) {
            $tin = trim((string) $record->tin_number);
            $validTin = $tin !== '' && preg_match($tinPattern, $tin) === 1;

            if (! $validTin) {
                $rejects[] = [$record->employee_number, trim($record->first_name.' '.$record->last_name), $tin, 'Invalid TIN'];

                if (! $this->option('allow-invalid-tin')) {
                    continue;
                }
            }

            $rows[] = [
                $record->employee_number,
                trim($record->first_name.' '.$record->last_name),
                $tin,
                $this->peso($record->gross_earned),
                $this->peso($record->taxable_income),
                $this->peso($record->tax_withheld),
                $this->peso($record->thirteenth_month_paid),
                $this->peso($record->other_benefits),
                $this->peso($record->contributions),
            ];
        }

        $rows[] = [
            'TOTAL', '', '',
            $this->peso($records->sum('gross_earned')),
            $this->peso($records->sum('taxable_income')),
            $this->peso($records->sum('tax_withheld')),
            $this->peso($records->sum('thirteenth_month_paid')),
            $this->peso($records->sum('other_benefits')),
            $this->peso($records->sum('contributions')),
        ];

        if ($rejects !== []) {
            $rejectCsv = collect($rejects)->map(fn (array $row) => implode(',', $row))->implode(PHP_EOL);

            if ($rejectPath = $this->option('rejects')) {
                file_put_contents($rejectPath, "Employee no,Name,TIN,Reason\n".$rejectCsv.PHP_EOL);
            }

            if (! $this->option('allow-invalid-tin')) {
                $this->warn(count($rejects).' employee(s) have an invalid TIN. Fix the TIN or pass --allow-invalid-tin. Rejected rows:');
                $this->line($rejectCsv);

                return self::FAILURE;
            }

            $this->warn(count($rejects).' employee(s) with an invalid TIN were included because --allow-invalid-tin was passed.');
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($path = $this->option('path')) {
            file_put_contents($path, $csv);
            $this->info('BIR 2316 data written to '.$path);

            return self::SUCCESS;
        }

        $this->line($csv);

        return self::SUCCESS;
    }

    private function peso($centavos): string
    {
        return number_format(((int) $centavos) / 100, 2, '.', '');
    }
}
