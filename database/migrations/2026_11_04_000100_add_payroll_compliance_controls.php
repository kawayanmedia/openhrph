<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll compliance controls: immutable revisions, audit events, the
 * year-to-date ledger, the de minimis catalogue and payroll accounting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'type')) {
                $table->string('type', 32)->default('regular')->after('cut_off');
            }

            if (! Schema::hasColumn('payrolls', 'meta')) {
                $table->json('meta')->nullable()->after('type');
            }

            if (! Schema::hasColumn('payrolls', 'warnings')) {
                $table->json('warnings')->nullable()->after('failed_reason');
            }

            if (! Schema::hasColumn('payrolls', 'input_hash')) {
                $table->string('input_hash', 64)->nullable()->after('warnings');
            }

            if (! Schema::hasColumn('payrolls', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('input_hash');
            }

            if (! Schema::hasColumn('payrolls', 'reopened_by')) {
                $table->unsignedBigInteger('reopened_by')->nullable()->after('reopened_at');
            }

            if (! Schema::hasColumn('payrolls', 'reopen_reason')) {
                $table->text('reopen_reason')->nullable()->after('reopened_by');
            }
        });

        Schema::table('payroll_details', function (Blueprint $table) {
            foreach ([
                'bonus' => 'bonus',
                'commission' => 'commission',
                'thirteenth_month' => 'thirteenth_month',
                'leave_conversion' => 'leave_conversion',
                'separation_pay' => 'separation_pay',
                'loans' => 'loans',
                'basic_earned' => 'basic_earned',
                'benefit_exempt' => 'benefit_exempt',
                'minimum_wage_exemption' => 'minimum_wage_exemption',
            ] as $column => $after) {
                if (! Schema::hasColumn('payroll_details', $column)) {
                    $table->bigInteger($column)->default(0);
                }
            }

            if (! Schema::hasColumn('payroll_details', 'warnings')) {
                $table->json('warnings')->nullable();
            }
        });

        if (! Schema::hasTable('payroll_detail_revisions')) {
            Schema::create('payroll_detail_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('payroll_id')->index();
                $table->unsignedInteger('revision')->default(1);
                $table->string('payroll_status', 32)->nullable();
                $table->string('engine_version', 32)->nullable();
                $table->unsignedBigInteger('employee_id');
                $table->bigInteger('basic_monthly_salary')->default(0);
                $table->bigInteger('basic_salary')->default(0);
                $table->bigInteger('daily_rate')->default(0);
                $table->bigInteger('gross_income')->default(0);
                $table->bigInteger('taxable_income')->default(0);
                $table->bigInteger('non_taxable_income')->default(0);
                $table->bigInteger('withholding_tax')->default(0);
                $table->bigInteger('sss_employee')->default(0);
                $table->bigInteger('sss_provident_employee')->default(0);
                $table->bigInteger('sss_employer')->default(0);
                $table->bigInteger('sss_provident_employer')->default(0);
                $table->bigInteger('sss_employer_ec_er')->default(0);
                $table->bigInteger('hdmf_employee')->default(0);
                $table->bigInteger('hdmf_employer')->default(0);
                $table->bigInteger('philhealth_employee')->default(0);
                $table->bigInteger('philhealth_employer')->default(0);
                $table->bigInteger('regular_pay')->default(0);
                $table->bigInteger('overtime_pay')->default(0);
                $table->bigInteger('night_pay')->default(0);
                $table->bigInteger('rest_day_pay')->default(0);
                $table->bigInteger('holiday_pay')->default(0);
                $table->bigInteger('allowance')->default(0);
                $table->bigInteger('incentive')->default(0);
                $table->bigInteger('total_employee')->default(0);
                $table->bigInteger('total_employer')->default(0);
                $table->bigInteger('total_deductions')->default(0);
                $table->bigInteger('net_pay')->default(0);
                $table->json('warnings')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['payroll_id', 'revision']);
            });
        }

        if (! Schema::hasTable('payroll_audit_events')) {
            Schema::create('payroll_audit_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('payroll_id')->index();
                $table->string('event', 32);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('reason')->nullable();
                $table->string('engine_version', 32)->nullable();
                $table->bigInteger('gross_amount')->default(0);
                $table->bigInteger('net_amount')->default(0);
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('employee_payroll_ytd')) {
            Schema::create('employee_payroll_ytd', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedSmallInteger('year');
                $table->bigInteger('basic_earned')->default(0);
                $table->bigInteger('gross_earned')->default(0);
                $table->bigInteger('taxable_income')->default(0);
                $table->bigInteger('tax_withheld')->default(0);
                $table->bigInteger('contributions')->default(0);
                $table->bigInteger('thirteenth_month_paid')->default(0);
                $table->bigInteger('other_benefits')->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'employee_id', 'year']);
            });
        }

        if (! Schema::hasTable('statutory_de_minimis')) {
            Schema::create('statutory_de_minimis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 32);
                $table->string('name', 128);
                $table->unsignedBigInteger('ceiling_centavos')->default(0);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('payroll_id')->index();
                $table->string('entry_no', 32);
                $table->date('entry_date');
                $table->string('memo', 255)->nullable();
                $table->string('status', 32)->default('posted');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'payroll_id', 'entry_no']);
            });
        }

        if (! Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('journal_entry_id')->index();
                $table->string('account_code', 32);
                $table->string('account_name', 128);
                $table->string('description', 255)->nullable();
                $table->bigInteger('debit_centavos')->default(0);
                $table->bigInteger('credit_centavos')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('statutory_de_minimis');
        Schema::dropIfExists('employee_payroll_ytd');
        Schema::dropIfExists('payroll_audit_events');
        Schema::dropIfExists('payroll_detail_revisions');
    }
};
