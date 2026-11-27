<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRMPSB committees, recruitment deliberations and the tenant link on the
 * legacy CSC service record so government service history is tenant-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrmpsb_committees')) {
            Schema::create('hrmpsb_committees', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 128);
                $table->string('reference_number', 96)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hrmpsb_members')) {
            Schema::create('hrmpsb_members', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('committee_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('external_name', 128)->nullable();
                $table->string('role', 32)->default('member');
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'committee_id']);
                $table->index(['company_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('recruitment_deliberations')) {
            Schema::create('recruitment_deliberations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('job_vacancy_id')->nullable();
                $table->unsignedBigInteger('committee_id')->nullable();
                $table->date('meeting_date');
                $table->string('reference_number', 96)->nullable();
                $table->unsignedBigInteger('minutes_document_id')->nullable();
                $table->text('recommendation')->nullable();
                $table->string('status', 32)->default('draft');
                $table->timestamps();

                $table->index(['company_id', 'job_vacancy_id']);
                $table->index(['company_id', 'status']);
            });
        }

        if (Schema::hasTable('service_records')) {
            if (! Schema::hasColumn('service_records', 'company_id')) {
                Schema::table('service_records', function (Blueprint $table): void {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                });
            }

            if (! Schema::hasColumn('service_records', 'plantilla_item_id')) {
                Schema::table('service_records', function (Blueprint $table): void {
                    $table->unsignedBigInteger('plantilla_item_id')->nullable()->index();
                });
            }

            if (! Schema::hasColumn('service_records', 'appointment_id')) {
                Schema::table('service_records', function (Blueprint $table): void {
                    $table->unsignedBigInteger('appointment_id')->nullable()->index();
                });
            }

            $this->backfillServiceRecordCompanies();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_records')) {
            foreach (['company_id', 'plantilla_item_id', 'appointment_id'] as $column) {
                if (Schema::hasColumn('service_records', $column)) {
                    Schema::table('service_records', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        Schema::dropIfExists('recruitment_deliberations');
        Schema::dropIfExists('hrmpsb_members');
        Schema::dropIfExists('hrmpsb_committees');
    }

    protected function backfillServiceRecordCompanies(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        DB::table('service_records')
            ->whereNull('company_id')
            ->whereNotNull('employee_id')
            ->orderBy('id')
            ->each(function ($record): void {
                $companyId = DB::table('employees')->where('id', $record->employee_id)->value('company_id');

                if ($companyId !== null) {
                    DB::table('service_records')->where('id', $record->id)->update([
                        'company_id' => $companyId,
                    ]);
                }
            });
    }
};
