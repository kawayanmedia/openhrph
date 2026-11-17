<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration for the deduction catalogue.
 *
 * `deductions` used to be a name only; every amount lived on the employee
 * assignment. These columns let an administrator define a deduction once per
 * country (fixed or percentage, on a chosen base, taxable or not) so the same
 * catalogue works outside the Philippine statutory set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deductions')) {
            return;
        }

        $columns = [
            'code' => fn (Blueprint $table) => $table->string('code', 32)->nullable(),
            'country_code' => fn (Blueprint $table) => $table->string('country_code', 2)->nullable()->index(),
            'type' => fn (Blueprint $table) => $table->string('type', 16)->default('voluntary'),
            'calculation' => fn (Blueprint $table) => $table->string('calculation', 16)->default('fixed'),
            'amount_centavos' => fn (Blueprint $table) => $table->bigInteger('amount_centavos')->default(0),
            'rate' => fn (Blueprint $table) => $table->decimal('rate', 10, 6)->nullable(),
            'base' => fn (Blueprint $table) => $table->string('base', 24)->nullable(),
            'reduces_taxable_income' => fn (Blueprint $table) => $table->boolean('reduces_taxable_income')->default(false),
            'is_active' => fn (Blueprint $table) => $table->boolean('is_active')->default(true),
            'sort_order' => fn (Blueprint $table) => $table->integer('sort_order')->default(0),
        ];

        foreach ($columns as $name => $definition) {
            if (Schema::hasColumn('deductions', $name)) {
                continue;
            }

            Schema::table('deductions', function (Blueprint $table) use ($definition): void {
                $definition($table);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('deductions')) {
            return;
        }

        foreach (['code', 'country_code', 'type', 'calculation', 'amount_centavos', 'rate', 'base', 'reduces_taxable_income', 'is_active', 'sort_order'] as $name) {
            if (! Schema::hasColumn('deductions', $name)) {
                continue;
            }

            Schema::table('deductions', function (Blueprint $table) use ($name): void {
                $table->dropColumn($name);
            });
        }
    }
};
