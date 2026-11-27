<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned plantilla and qualification standards.
 *
 * A plantilla version is the approved staffing pattern. Items belong to a
 * version so an abolished or reclassified item never rewrites history. The
 * existing `plantilla_items` table is extended in place so the legacy
 * government screens keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plantilla_versions')) {
            Schema::create('plantilla_versions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('version', 32);
                $table->string('name', 128)->nullable();
                $table->string('approval_reference', 128)->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('document_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'version']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('qualification_standards')) {
            Schema::create('qualification_standards', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('position_id')->nullable();
                $table->string('position_title', 128)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->text('education')->nullable();
                $table->text('training')->nullable();
                $table->text('experience')->nullable();
                $table->text('eligibility')->nullable();
                $table->text('license')->nullable();
                $table->unsignedInteger('training_hours')->nullable();
                $table->decimal('experience_years', 5, 2)->nullable();
                $table->boolean('requires_eligibility')->default(false);
                $table->boolean('requires_license')->default(false);
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->string('status', 32)->default('draft');
                $table->string('source_reference', 191)->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'position_id', 'status']);
                $table->index(['company_id', 'position_title']);
            });
        }

        if (! Schema::hasTable('qualification_screenings')) {
            Schema::create('qualification_screenings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('applicant_id')->nullable();
                $table->unsignedBigInteger('qualification_standard_id');
                $table->unsignedInteger('standard_version')->default(1);
                $table->json('evidence')->nullable();
                $table->string('result', 32);
                $table->json('deficiencies')->nullable();
                $table->unsignedBigInteger('screened_by')->nullable();
                $table->timestamp('screened_at');
                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'qualification_standard_id'], 'qualification_screenings_standard_index');
            });
        }

        if (Schema::hasTable('plantilla_items')) {
            $columns = [
                'plantilla_version_id' => fn (Blueprint $table) => $table->unsignedBigInteger('plantilla_version_id')->nullable()->index(),
                'position_id' => fn (Blueprint $table) => $table->unsignedBigInteger('position_id')->nullable()->index(),
                'organization_unit_id' => fn (Blueprint $table) => $table->unsignedBigInteger('organization_unit_id')->nullable()->index(),
                'funding_source' => fn (Blueprint $table) => $table->string('funding_source', 64)->nullable(),
                'status' => fn (Blueprint $table) => $table->string('status', 32)->default('authorized')->index(),
                'effective_from' => fn (Blueprint $table) => $table->date('effective_from')->nullable(),
                'effective_until' => fn (Blueprint $table) => $table->date('effective_until')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (Schema::hasColumn('plantilla_items', $column)) {
                    continue;
                }

                Schema::table('plantilla_items', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qualification_screenings');
        Schema::dropIfExists('qualification_standards');
        Schema::dropIfExists('plantilla_versions');

        if (! Schema::hasTable('plantilla_items')) {
            return;
        }

        foreach (['plantilla_version_id', 'position_id', 'organization_unit_id', 'funding_source', 'status', 'effective_from', 'effective_until'] as $column) {
            if (Schema::hasColumn('plantilla_items', $column)) {
                Schema::table('plantilla_items', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
