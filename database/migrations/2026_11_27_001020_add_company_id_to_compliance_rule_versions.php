<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-scope compliance rule versions so one tenant's configurations are
 * never visible to another.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('compliance_rule_versions', 'company_id')) {
            Schema::table('compliance_rule_versions', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('compliance_rule_versions', 'company_id')) {
            Schema::table('compliance_rule_versions', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
    }
};
