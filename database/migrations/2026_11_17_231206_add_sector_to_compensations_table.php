<?php

use App\Support\Payroll\CompensationCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('compensations', 'sector')) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->string('sector', 16)->nullable()->after('company_id')->index();
            });
        }

        // Populate the new column from the catalogue. Passing no sector keeps
        // every shared row and tags the Government/Private-only rows.
        CompensationCatalogue::sync();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('compensations', 'sector')) {
            Schema::table('compensations', function (Blueprint $table): void {
                $table->dropColumn('sector');
            });
        }
    }
};
