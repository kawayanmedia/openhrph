<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollGroup;
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
        $groups = PayrollGroup::withoutGlobalScopes()->whereIn('company_id', $companies)->pluck('id');
        $payrolls = Payroll::withoutGlobalScopes()->whereIn('company_id', $companies)->pluck('id');
        $employees = Employee::withoutGlobalScopes()->whereIn('company_id', $companies)->pluck('id');

        DB::table('payroll_details')->whereIn('payroll_id', $payrolls)->delete();
        DB::table('timesheets')->whereIn('employee_id', $employees)->delete();
        DB::table('payrolls')->whereIn('id', $payrolls)->delete();
        DB::table('employees')->whereIn('id', $employees)->delete();
        DB::table('offices')->whereIn('company_id', $companies)->delete();
        DB::table('payroll_groups')->whereIn('id', $groups)->delete();
        DB::table('groups')->whereIn('company_id', $companies)->delete();
        DB::table('companies')->whereIn('id', $companies)->delete();
        User::query()->where('email', config('payroll.e2e.user_email'))->delete();

        $this->info('E2E fixtures removed.');

        return self::SUCCESS;
    }
}
