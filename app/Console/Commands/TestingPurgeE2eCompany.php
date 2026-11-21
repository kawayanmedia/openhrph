<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestingPurgeE2eCompany extends Command
{
    protected $signature = 'testing:purge-e2e-company';

    protected $description = 'Remove the isolated company created for browser tests';

    public function handle(): int
    {
        $prefix = (string) config('payroll.e2e.company_prefix', 'E2E ');

        $companies = Company::withoutGlobalScopes()->where('name', 'like', $prefix.'%')->pluck('id');

        DB::table('journal_entry_lines')->whereIn('company_id', $companies)->delete();
        DB::table('journal_entries')->whereIn('company_id', $companies)->delete();
        DB::table('payroll_audit_events')->whereIn('company_id', $companies)->delete();
        DB::table('payroll_detail_deductions')->whereIn('company_id', $companies)->delete();
        DB::table('payroll_details')->whereIn('company_id', $companies)->delete();
        DB::table('employees_deductions')->whereIn('company_id', $companies)->delete();
        DB::table('employees_loans')->whereIn('company_id', $companies)->delete();
        DB::table('employees_compensations')->whereIn('company_id', $companies)->delete();
        DB::table('deductions')->whereIn('company_id', $companies)->delete();
        DB::table('loans')->whereIn('company_id', $companies)->delete();
        DB::table('compensations')->whereIn('company_id', $companies)->delete();
        DB::table('rates')->whereIn('company_id', $companies)->delete();
        DB::table('schedules')->whereIn('company_id', $companies)->delete();
        DB::table('timesheets')->whereIn('company_id', $companies)->delete();
        DB::table('payrolls')->whereIn('company_id', $companies)->delete();
        DB::table('payroll_dates')->whereIn('company_id', $companies)->delete();
        DB::table('employees_schedules')->whereIn('company_id', $companies)->delete();
        DB::table('employees')->whereIn('company_id', $companies)->delete();
        DB::table('offices')->whereIn('company_id', $companies)->delete();
        DB::table('departments')->whereIn('company_id', $companies)->delete();
        DB::table('employment_types')->whereIn('company_id', $companies)->delete();
        DB::table('holidays')->whereIn('company_id', $companies)->delete();
        DB::table('settings')->whereIn('company_id', $companies)->delete();
        DB::table('payroll_groups')->whereIn('company_id', $companies)->delete();
        DB::table('groups')->whereIn('company_id', $companies)->delete();
        DB::table('company_user')->whereIn('company_id', $companies)->delete();
        User::query()->whereIn('company_id', $companies)
            ->orWhereIn('email', [
                config('payroll.e2e.user_email'),
                config('payroll.e2e.onboarding_user_email'),
            ])
            ->delete();
        DB::table('companies')->whereIn('id', $companies)->delete();

        $this->info('E2E fixtures removed.');

        return self::SUCCESS;
    }
}
