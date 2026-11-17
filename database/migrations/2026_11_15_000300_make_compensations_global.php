<?php

use App\Support\Payroll\CompensationCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the compensation catalogue from per-company copies to a single
 * system-wide catalogue.
 *
 * A row with a NULL company_id is a system row every company shares. A row
 * with a company_id is that company's override of the system row with the same
 * code (amount and non-taxable ceiling are the values a company overrides).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compensations')) {
            return;
        }

        $this->prepareSchema();
        $this->mergeCompanyCatalogues();

        CompensationCatalogue::sync();
    }

    public function down(): void
    {
        if (! Schema::hasTable('compensations')) {
            return;
        }

        if (Schema::hasIndex('compensations', ['company_id', 'code'])) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->dropIndex(['company_id', 'code']);
            });
        }

        if (Schema::hasIndex('compensations', ['group'])) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->dropIndex(['group']);
            });
        }

        // The merged duplicates cannot be told apart from the system rows, so
        // the per-company catalogues are not recreated.
    }

    /**
     * Make company_id nullable and index the columns the catalogue queries use.
     */
    private function prepareSchema(): void
    {
        if (Schema::hasIndex('compensations', ['company_id', 'group'])) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->dropIndex(['company_id', 'group']);
            });
        }

        if (Schema::hasColumn('compensations', 'company_id') && ! $this->companyIdIsNullable()) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->integer('company_id')->nullable()->change();
            });
        }

        if (! Schema::hasIndex('compensations', ['group'])) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->index('group');
            });
        }

        if (! Schema::hasIndex('compensations', ['company_id', 'code'])) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->index(['company_id', 'code']);
            });
        }
    }

    /**
     * Collapse the per-company copies into one system row per catalogue code.
     *
     * A company whose values differ from the system row keeps its copy as an
     * override; identical copies are repointed and removed.
     */
    private function mergeCompanyCatalogues(): void
    {
        if (! Schema::hasTable('employees_compensations')) {
            return;
        }

        $systemCodes = [];

        foreach (CompensationCatalogue::rows() as $row) {
            $systemCodes[self::normaliseCode($row['code'])] = $row['code'];
        }

        $canonical = [];

        $rows = DB::table('compensations')
            ->orderBy('id')
            ->get(['id', 'company_id', 'code', 'amount', 'maximum_non_tax']);

        foreach ($rows as $row) {
            $code = self::normaliseCode($row->code);

            if (! isset($systemCodes[$code])) {
                continue;
            }

            if (! isset($canonical[$code])) {
                $canonical[$code] = $row;

                continue;
            }

            /** @var object{id: int, company_id: int|null, code: string, amount: float|int|null, maximum_non_tax: int|null} $keeper */
            $keeper = $canonical[$code];

            if (self::centavos($row->amount) !== self::centavos($keeper->amount)
                || self::centavos($row->maximum_non_tax) !== self::centavos($keeper->maximum_non_tax)) {
                continue;
            }

            DB::table('employees_compensations')
                ->where('compensation_id', $row->id)
                ->update(['compensation_id' => $keeper->id]);

            DB::table('compensations')->where('id', $row->id)->delete();
        }

        foreach ($canonical as $code => $row) {
            DB::table('compensations')
                ->where('id', $row->id)
                ->update([
                    'company_id' => null,
                    'code' => $systemCodes[$code],
                ]);
        }
    }

    private function companyIdIsNullable(): bool
    {
        try {
            $column = collect(Schema::getColumns('compensations'))
                ->firstWhere('name', 'company_id');

            return ($column['nullable'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function normaliseCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    private static function centavos(mixed $value): int
    {
        return (int) round((float) ($value ?? 0));
    }
};
