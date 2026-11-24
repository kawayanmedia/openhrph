<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company payroll bank-file layouts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_file_templates')) {
            Schema::create('bank_file_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name', 128);
                $table->string('bank_name', 128)->nullable();
                $table->string('delimiter', 4)->default(',');
                $table->json('columns');
                $table->json('header_rows')->nullable();
                $table->json('footer_rows')->nullable();
                $table->boolean('amount_in_centavos')->default(false);
                $table->string('date_format', 16)->default('Ymd');
                $table->boolean('include_control_total')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('companies') || ! Schema::hasTable('bank_file_templates')) {
            return;
        }

        $columns = json_encode([
            ['source' => 'employee_number', 'label' => 'Employee No'],
            ['source' => 'account_name', 'label' => 'Account Name'],
            ['source' => 'account_number', 'label' => 'Account Number'],
            ['source' => 'bank_name', 'label' => 'Bank'],
            ['source' => 'amount', 'label' => 'Amount'],
        ]);

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $exists = DB::table('bank_file_templates')
                ->where('company_id', $companyId)
                ->where('name', 'Generic Bank Credit')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('bank_file_templates')->insert([
                'company_id' => $companyId,
                'name' => 'Generic Bank Credit',
                'delimiter' => ',',
                'columns' => $columns,
                'amount_in_centavos' => false,
                'date_format' => 'Ymd',
                'include_control_total' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_file_templates');
    }
};
