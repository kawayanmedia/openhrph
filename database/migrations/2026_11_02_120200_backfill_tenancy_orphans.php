<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $primary = DB::table('companies')->orderBy('id')->value('id');

        if ($primary === null) {
            return;
        }

        foreach (['leave_applications', 'plantilla_items'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            $remaining = DB::table($table)->whereNull('company_id')->count();

            if ($remaining > 0) {
                Log::warning("Assigning {$remaining} legacy row(s) in [{$table}] to company [{$primary}].");
                DB::table($table)->whereNull('company_id')->update(['company_id' => $primary]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
