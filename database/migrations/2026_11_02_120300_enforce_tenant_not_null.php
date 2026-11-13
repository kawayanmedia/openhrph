<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that intentionally contain global rows.
     *
     * @var array<int, string>
     */
    private array $globalTables = [
        'users',
        'nova_settings',
        'statutory_pagibig',
        'statutory_philhealth',
        'statutory_sss_brackets',
        'statutory_tax_brackets',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = collect(DB::select(
            "SELECT TABLE_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id' ORDER BY TABLE_NAME"
        ))->pluck('name')->diff($this->globalTables);

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->whereNull('company_id')->exists()) {
                continue;
            }

            $definition = DB::selectOne(
                "SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'company_id'",
                [$table]
            );

            if ($definition === null) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE `{$table}` MODIFY `company_id` {$definition->type} NOT NULL");
            } catch (Throwable $e) {
                Log::warning("Unable to enforce company_id NOT NULL on [{$table}]: {$e->getMessage()}");
            }
        }
    }

    public function down(): void
    {
        //
    }
};
