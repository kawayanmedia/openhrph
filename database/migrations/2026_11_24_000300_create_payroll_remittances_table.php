<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filing and payment tracking for statutory remittances per payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_remittances')) {
            return;
        }

        Schema::create('payroll_remittances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('payroll_id')->index();
            $table->string('agency', 32);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->date('due_date')->nullable();
            $table->bigInteger('employee_amount')->default(0);
            $table->bigInteger('employer_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->string('status', 16)->default('pending');
            $table->string('reference_number', 64)->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['payroll_id', 'agency']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_remittances');
    }
};
