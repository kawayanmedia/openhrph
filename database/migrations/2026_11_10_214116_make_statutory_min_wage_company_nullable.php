<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('statutory_min_wage')) {
            DB::statement('ALTER TABLE statutory_min_wage MODIFY company_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('statutory_min_wage')) {
            DB::statement('DELETE FROM statutory_min_wage WHERE company_id IS NULL');
            DB::statement('ALTER TABLE statutory_min_wage MODIFY company_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
