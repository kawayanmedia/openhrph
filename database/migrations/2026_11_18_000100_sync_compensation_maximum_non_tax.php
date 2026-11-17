<?php

use App\Support\Payroll\CompensationCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Publish the BIR maximum non-taxable amounts on the shared compensation
 * catalogue: the annualised de minimis ceilings from RR 29-2025 and the
 * ₱90,000 13th month and other benefits exclusion. Company overrides keep the
 * ceiling they were given.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compensations')) {
            return;
        }

        CompensationCatalogue::sync(now());
    }

    public function down(): void
    {
        // The refreshed ceilings cannot be told apart from a company's own edits.
    }
};
