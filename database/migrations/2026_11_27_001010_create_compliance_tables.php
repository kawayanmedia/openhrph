<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance source register with versioned, reviewed rule configurations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compliance_sources')) {
            Schema::create('compliance_sources', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('authority', 128);
                $table->string('title', 191);
                $table->string('issuance_number', 96)->nullable();
                $table->string('official_url', 255)->nullable();
                $table->date('published_at')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['authority', 'issuance_number'], 'compliance_sources_authority_index');
            });
        }

        if (! Schema::hasTable('compliance_rules')) {
            Schema::create('compliance_rules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('compliance_source_id');
                $table->string('code', 96)->unique();
                $table->string('name', 191);
                $table->string('sector', 32)->nullable();
                $table->string('organization_type', 64)->nullable();
                $table->string('classification_code', 64)->nullable();
                $table->string('version', 64);
                $table->date('effective_from');
                $table->date('effective_until')->nullable();
                $table->string('status', 32)->default('draft');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['sector', 'status', 'effective_from'], 'compliance_rules_sector_status_index');
            });
        }

        if (! Schema::hasTable('compliance_rule_versions')) {
            Schema::create('compliance_rule_versions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('compliance_rule_id');
                $table->string('version', 64);
                $table->json('configuration');
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('change_summary')->nullable();
                $table->timestamps();

                $table->unique(['compliance_rule_id', 'version'], 'compliance_rule_versions_rule_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_rule_versions');
        Schema::dropIfExists('compliance_rules');
        Schema::dropIfExists('compliance_sources');
    }
};
