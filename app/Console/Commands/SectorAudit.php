<?php

namespace App\Console\Commands;

use App\Enums\Sector;
use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports data that does not belong to the active installation sector.
 *
 * Read-only. Use `--strict` in CI to fail when the installation holds rows for
 * the other sector, and `openhr:sector:prune` to deactivate catalogue rows.
 */
class SectorAudit extends Command
{
    protected $signature = 'openhr:sector:audit
        {--json : Write machine-readable JSON}
        {--strict : Fail when any foreign-sector row is found}';

    protected $description = 'Report government or private data that does not match the installation sector';

    public function handle(): int
    {
        $sector = Installation::sectorEnum();

        if ($sector === null) {
            $this->warn('No installation sector is configured. Run `php artisan openhr:install` first.');

            return self::SUCCESS;
        }

        $foreign = $sector === Sector::Government ? Sector::Private : Sector::Government;
        $findings = [];

        $governmentTables = [
            'statutory_gsis' => 'GSIS statutory schedule',
            'plantilla_items' => 'plantilla items',
            'salary_grades' => 'salary grades',
            'employees_plantilla' => 'employee plantilla assignments',
            'nosas' => 'notices of salary adjustment',
            'service_records' => 'service records',
            'leave_cards' => 'leave cards',
        ];

        if ($sector === Sector::Private) {
            foreach ($governmentTables as $table => $label) {
                $this->addTableFinding($findings, $table, $label, 'Government');
            }
        }

        if ($sector === Sector::Government) {
            $this->addTableFinding($findings, 'statutory_sss_brackets', 'SSS statutory brackets', 'Private');
        }

        foreach (['compensations', 'leave_types'] as $table) {
            $this->addCatalogueFinding($findings, $table, $foreign);
        }

        $mismatchedCompanies = $this->companyTypeCount($sector);

        if ($mismatchedCompanies > 0) {
            $findings[] = [
                'area' => 'companies',
                'table' => 'companies',
                'rows' => $mismatchedCompanies,
                'detail' => "Companies not marked {$sector->value}",
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'sector' => $sector->value,
                'findings' => $findings,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info("Installation sector: {$sector->label()}");

            if ($findings === []) {
                $this->info('No foreign-sector data found.');
            } else {
                $this->table(
                    ['Area', 'Table', 'Rows', 'Detail'],
                    array_map(
                        static fn (array $finding): array => [
                            $finding['area'],
                            $finding['table'],
                            $finding['rows'],
                            $finding['detail'],
                        ],
                        $findings,
                    ),
                );
            }
        }

        if ($this->option('strict') && $findings !== []) {
            $this->error('Foreign-sector data found. Review it or run `openhr:sector:prune --apply`.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{area: string, table: string, rows: int, detail: string}>  $findings
     */
    private function addTableFinding(array &$findings, string $table, string $label, string $sector): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->count();

        if ($rows > 0) {
            $findings[] = [
                'area' => $sector,
                'table' => $table,
                'rows' => $rows,
                'detail' => "{$label} rows exist in a ".Installation::sector().' installation',
            ];
        }
    }

    /**
     * @param  array<int, array{area: string, table: string, rows: int, detail: string}>  $findings
     */
    private function addCatalogueFinding(array &$findings, string $table, Sector $foreign): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sector')) {
            return;
        }

        $rows = DB::table($table)
            ->whereRaw('lower(sector) = ?', [strtolower($foreign->value)])
            ->count();

        if ($rows > 0) {
            $findings[] = [
                'area' => $foreign->label(),
                'table' => $table,
                'rows' => $rows,
                'detail' => "{$foreign->label()} catalogue rows",
            ];
        }
    }

    private function companyTypeCount(Sector $sector): int
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'type')) {
            return 0;
        }

        return DB::table('companies')
            ->where(function ($query) use ($sector): void {
                $query->whereNull('type')
                    ->orWhereRaw('lower(type) <> ?', [strtolower($sector->value)]);
            })
            ->count();
    }
}
