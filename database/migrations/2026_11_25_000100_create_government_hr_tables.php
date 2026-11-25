<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public-sector HR records: NOSA, service record and leave card.
 *
 * These tables were never ported from the legacy application, so the
 * government screens had models without a schema. They stay sector-scoped:
 * a Private installation simply never writes to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nosas')) {
            Schema::create('nosas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('office_id')->nullable();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('plantilla_item_id')->nullable();
                $table->string('item_number', 32)->nullable();
                $table->tinyInteger('from_salary_grade')->nullable();
                $table->tinyInteger('from_step')->nullable();
                $table->string('from_salary_grade_year', 4)->nullable();
                $table->double('from_monthly_salary', 15, 2)->nullable();
                $table->tinyInteger('to_salary_grade')->nullable();
                $table->tinyInteger('to_step')->nullable();
                $table->string('to_salary_grade_year', 4)->nullable();
                $table->double('to_monthly_salary', 15, 2)->nullable();
                $table->date('effective_date')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['employee_id', 'effective_date']);
            });
        }

        if (! Schema::hasTable('service_records')) {
            Schema::create('service_records', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('date_from', 32)->nullable();
                $table->string('date_to', 32)->nullable();
                $table->string('designation', 128)->nullable();
                $table->string('status', 32)->nullable();
                $table->string('salary', 32)->nullable();
                $table->string('office_entity', 128)->nullable();
                $table->string('branch', 128)->nullable();
                $table->text('remarks')->nullable();
                $table->string('lwop', 32)->nullable();
                $table->date('separation_date')->nullable();
                $table->string('separation_cause', 128)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index('employee_id');
            });
        }

        if (! Schema::hasTable('leave_cards')) {
            Schema::create('leave_cards', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('name', 128)->nullable();
                $table->string('code', 32)->nullable();
                $table->string('account_number', 32)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index('employee_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_cards');
        Schema::dropIfExists('service_records');
        Schema::dropIfExists('nosas');
    }
};
