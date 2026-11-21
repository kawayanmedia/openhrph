<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A real leave ledger: policies define the entitlement, the ledger records
 * every movement, and the balance table is a rebuildable projection.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_policies')) {
            Schema::create('leave_policies', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('company_id');
                $table->integer('leave_type_id');
                $table->decimal('entitlement_days', 8, 2)->default(0);
                $table->string('accrual', 16)->default('annual'); // annual|monthly|none
                $table->boolean('prorate_first_year')->default(true);
                $table->decimal('carry_over_days', 8, 2)->default(0);
                $table->integer('expiry_months')->default(0);
                $table->boolean('half_day_allowed')->default(true);
                $table->boolean('is_paid')->default(true);
                $table->integer('min_service_months')->default(0);
                $table->boolean('requires_evidence')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'leave_type_id'], 'leave_policies_company_type_unique');
            });
        }

        if (! Schema::hasTable('leave_ledger')) {
            Schema::create('leave_ledger', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('company_id');
                $table->integer('employee_id');
                $table->integer('leave_type_id');
                $table->integer('period_year');
                $table->string('movement', 24); // accrual|availment|adjustment|carry_over|expiry|monetization|reversal
                $table->decimal('days', 8, 2);
                $table->string('reference', 64)->nullable();
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'employee_id', 'leave_type_id', 'period_year'], 'leave_ledger_employee_year_index');
                $table->unique(['company_id', 'employee_id', 'leave_type_id', 'movement', 'reference'], 'leave_ledger_movement_reference_unique');
            });
        }

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('company_id');
                $table->integer('employee_id');
                $table->integer('leave_type_id');
                $table->integer('year');
                $table->decimal('accrued', 8, 2)->default(0);
                $table->decimal('used', 8, 2)->default(0);
                $table->decimal('carried', 8, 2)->default(0);
                $table->decimal('expired', 8, 2)->default(0);
                $table->decimal('adjusted', 8, 2)->default(0);
                $table->decimal('balance', 8, 2)->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'employee_id', 'leave_type_id', 'year'], 'leave_balances_employee_year_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_ledger');
        Schema::dropIfExists('leave_policies');
    }
};
