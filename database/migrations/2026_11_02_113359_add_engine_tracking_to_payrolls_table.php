<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'engine_version')) {
                $table->string('engine_version', 32)->nullable()->after('status');
            }

            if (! Schema::hasColumn('payrolls', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('engine_version');
            }

            if (! Schema::hasColumn('payrolls', 'processed_employees')) {
                $table->unsignedInteger('processed_employees')->default(0)->after('processed_at');
            }

            if (! Schema::hasColumn('payrolls', 'total_employees')) {
                $table->unsignedInteger('total_employees')->default(0)->after('processed_employees');
            }

            if (! Schema::hasColumn('payrolls', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('total_employees');
            }

            if (! Schema::hasColumn('payrolls', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('payrolls', 'failed_reason')) {
                $table->text('failed_reason')->nullable()->after('approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            foreach (['engine_version', 'processed_at', 'processed_employees', 'total_employees', 'approved_at', 'approved_by', 'failed_reason'] as $column) {
                if (Schema::hasColumn('payrolls', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
