<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Sectors\Catalogues\DocumentCatalogue;
use App\Sectors\SectorManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Reports the sector's employee document and identifier checklist.
 *
 * The application does not store uploaded documents yet, so items without a
 * matching employee column are reported as "manual". Identifier codes are
 * checked against the employee master data.
 */
class PayrollDocuments extends Command
{
    protected $signature = 'payroll:documents
        {--company= : Limit to one company id}
        {--format=table : table or csv}';

    protected $description = 'Report the sector employee document and identifier checklist';

    public function handle(): int
    {
        $policy = app(SectorManager::class)->current();
        $query = Employee::withoutTenant()->whereNull('date_resigned');

        if ($company = $this->option('company')) {
            $query->where('company_id', (int) $company);
        }

        $total = (clone $query)->count();

        $rows = [];
        $seen = [];

        foreach (DocumentCatalogue::requirements($policy->sector()) as $document) {
            $code = strtoupper((string) ($document['code'] ?? ''));
            $seen[$code] = true;
            $column = $this->columnFor($code);

            $rows[] = [
                $code,
                (string) ($document['name'] ?? $code),
                ($document['required'] ?? false) ? 'Yes' : 'No',
                $column === null ? 'Manual' : $this->coverage($column, $query, $total),
            ];
        }

        foreach ((array) ($policy->statutory()['required_identifiers'] ?? []) as $column) {
            $code = strtoupper((string) $column);

            if (isset($seen[$code])) {
                continue;
            }

            $rows[] = [$code, $this->label($column), 'Yes', $this->coverage((string) $column, $query, $total)];
        }

        if ($rows === []) {
            $this->info('No document checklist is configured for '.$policy->label().'.');

            return self::SUCCESS;
        }

        if (strtolower((string) $this->option('format')) === 'csv') {
            $this->line(collect(['Code', 'Document', 'Required', 'Coverage'])->implode(','));
            foreach ($rows as $row) {
                $this->line(collect($row)->map(fn ($value): string => '"'.str_replace('"', '""', (string) $value).'"')->implode(','));
            }

            return self::SUCCESS;
        }

        $this->line('Sector: '.$policy->label().' · Employees: '.$total);
        $this->table(['Code', 'Document', 'Required', 'Coverage'], $rows);

        return self::SUCCESS;
    }

    private function coverage(string $column, $query, int $total): string
    {
        if (! Schema::hasColumn('employees', $column)) {
            return 'Missing column';
        }

        if ($total === 0) {
            return 'No employees';
        }

        $filled = (clone $query)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->count();

        return $filled.' of '.$total;
    }

    private function columnFor(string $code): ?string
    {
        return match ($code) {
            'TIN_NUMBER' => 'tin_number',
            'SSS_NUMBER' => 'sss_number',
            'GSIS_NUMBER' => 'gsis_number',
            'PHILHEALTH_NUMBER' => 'philhealth_number',
            'HDMF_NUMBER' => 'hdmf_number',
            default => null,
        };
    }

    private function label(string $column): string
    {
        return match ($column) {
            'tin_number' => 'TIN Number',
            'sss_number' => 'SSS Number',
            'gsis_number' => 'GSIS BP Number',
            'philhealth_number' => 'PhilHealth Number',
            'hdmf_number' => 'Pag-IBIG Number',
            default => ucwords(str_replace('_', ' ', $column)),
        };
    }
}
