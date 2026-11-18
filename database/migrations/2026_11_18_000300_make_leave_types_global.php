<?php

use App\Sectors\Catalogues\LeaveTypeCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the leave types from per-company copies to a single system-wide
 * catalogue.
 *
 * A row with a NULL company_id is a system row every company shares. A row with
 * a company_id is that company's override of the system row with the same name,
 * or a leave type only that company uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $this->prepareSchema();
        $this->mergeCompanyCatalogues();

        LeaveTypeCatalogue::sync();
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        if (Schema::hasColumn('leave_types', 'sector')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->dropColumn('sector');
            });
        }

        // The merged duplicates cannot be told apart from the system rows, so
        // the per-company catalogues are not recreated.
    }

    /**
     * Make company_id nullable and add the sector the row applies to.
     */
    private function prepareSchema(): void
    {
        if (Schema::hasColumn('leave_types', 'company_id') && ! $this->companyIdIsNullable()) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('leave_types', 'sector')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->string('sector', 16)->nullable()->after('company_id')->index();
            });
        }
    }

    /**
     * Collapse the per-company copies into one system row per leave type name.
     */
    private function mergeCompanyCatalogues(): void
    {
        $keepers = [];

        $rows = DB::table('leave_types')
            ->orderBy('id')
            ->get(['id', 'company_id', 'name']);

        foreach ($rows as $row) {
            $name = LeaveTypeCatalogue::normalise($row->name);

            if ($name === '') {
                continue;
            }

            if (! isset($keepers[$name])) {
                $keepers[$name] = $row;

                continue;
            }

            /** @var object{id: int} $keeper */
            $keeper = $keepers[$name];

            $this->repointReferences((int) $row->id, (int) $keeper->id);

            DB::table('leave_types')->where('id', $row->id)->delete();
        }

        foreach ($keepers as $row) {
            DB::table('leave_types')
                ->where('id', $row->id)
                ->update(['company_id' => null]);
        }
    }

    /**
     * Point the records that used a duplicate at the surviving system row.
     */
    private function repointReferences(int $from, int $to): void
    {
        foreach (['leave_requests', 'timesheets'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'leave_type_id')) {
                continue;
            }

            DB::table($table)->where('leave_type_id', $from)->update(['leave_type_id' => $to]);
        }
    }

    private function companyIdIsNullable(): bool
    {
        try {
            $column = collect(Schema::getColumns('leave_types'))
                ->firstWhere('name', 'company_id');

            return ($column['nullable'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
    }
};
