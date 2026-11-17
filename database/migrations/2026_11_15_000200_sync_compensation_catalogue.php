<?php

use App\Support\Payroll\CompensationCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring every existing company's compensation catalogue up to date: the BIR tax
 * class on each row, the de minimis lookup codes the payroll engine matches on,
 * and the compensation types that were missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compensations') || ! Schema::hasTable('companies')) {
            return;
        }

        DB::table('companies')->orderBy('id')->select('id')->chunkById(100, function ($companies): void {
            foreach ($companies as $company) {
                CompensationCatalogue::sync(now());
            }
        });
    }

    public function down(): void
    {
        // The refreshed classification and the added rows cannot be told apart
        // from a company's own edits, so the backfill is not reversed.
    }
};
