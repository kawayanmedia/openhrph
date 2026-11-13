<?php

namespace App\Http\Controllers;

use App\Actions\GeneratePayrollDateAction;
use App\Mail\AccountCreated;
use App\Models\Company;
use App\Models\DeductionPagibig;
use App\Models\DeductionPhilHealth;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\Office;
use App\Models\PayrollGroup;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SetupController extends Controller
{
    public function setup($id)
    {
        // https://app.hris.test/setup-company/7
        // Create 1k employees for sample
        // while ($i <= 300) {

        //     $newEmpoyee = $employe->replicate();
        //     $newEmpoyee->save();
        //     $i ++;
        // }

        // Check if company id is already verified
        $company = Company::query()
            ->where('uuid', request('uuid'))
            ->where('id', $id)
            ->first();

        abort_unless($company !== null, 404);

        app(TenantContext::class)->set($company->getKey());

        if ($company) {
            if ($company->setup_done == false) {
                // Setup all needed settings

                // setup holidays
                foreach (config('holidays.'.date('Y')) as $holiday) {
                    $h = Holiday::firstOrNew(['company_id' => $id, 'date' => $holiday['date']]);
                    $h->year = $holiday['year'];
                    $h->name = $holiday['name'];
                    $h->holiday_type = $holiday['holiday_type'];
                    $h->code = $holiday['code'];
                    $h->save();
                }
                $company->setup_done = true;
                $company->save();

                // Setup settings
                $companySettings = [
                    'company_name' => 'Your Company Name '.$id,
                    'trade_name' => 'Your Trade Name '.$id,
                    'type' => 'Private',
                    'tin' => '',
                    'rdo' => '',
                    'sss' => '',

                    'hdmf' => '',
                    'phil_health' => '',

                    // 'sss' => 'Private',
                    // 'sss' => 'Private',

                    'maximum_pairing_range' => '20',
                    'minimum_pairing_range' => '2',
                    'night_shift_differential_start' => '22:00',
                    'night_shift_differential_end' => '06:00',

                    'daily_paid' => PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
                    'days_year_no_weeked' => PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
                    'days_year_with_weeked' => PayrollGroup::NUMBER_WORKING_DAYS_MONSAT,
                    'deduct_tax' => 1,

                    'sss_schedule' => 'every_pay',
                    'phil_health_schedule' => 'every_pay',
                    'hdmf_schedule' => 'every_pay',
                ];

                foreach ($companySettings as $key => $value) {
                    DB::table('settings')->insert(['company_id' => $company->id, 'key' => $key, 'value' => $value]);
                }

                // Create new Location or office
                $office = Office::firstOrNew(['company_id' => $company->id, 'name' => 'NCR']);
                $office->code = 'off';
                $office->location = 'internal';
                $office->disbursing_officer = '';
                $office->save();

                // Create new Department
                $department = Department::firstOrNew(['company_id' => $company->id, 'name' => 'HRM Office']);
                $department->code = 'HRMO';
                $department->save();

                // Payroll group
                $payrollGroup = PayrollGroup::firstOrNew(['company_id' => $company->id, 'name' => 'Default Kinsenas Katapusan']);
                $payrollGroup->days_year = PayrollGroup::NUMBER_WORKING_DAYS_MONFRI;
                $payrollGroup->frequency = PayrollGroup::FREQUENCY_SEMI_MONTHLY;
                $payrollGroup->first_cut_off = 10;
                $payrollGroup->first_pay_date = 15;
                $payrollGroup->second_cut_off = 25;
                $payrollGroup->second_pay_date = 30;
                $payrollGroup->first_run = 15;
                $payrollGroup->second_run = 30;
                $payrollGroup->pay_date_non_working = PayrollGroup::NON_WORKING_DATE_BEFORE;

                $payrollGroup->tax_schedule = PayrollGroup::PAY_SCHEDULE_EVERYPAY;
                $payrollGroup->sss_schedule = PayrollGroup::PAY_SCHEDULE_EVERYPAY;
                $payrollGroup->phil_health_schedule = PayrollGroup::PAY_SCHEDULE_EVERYPAY;
                $payrollGroup->hdmf_schedule = PayrollGroup::PAY_SCHEDULE_EVERYPAY;

                $payrollGroup->compute_tardiness = false;
                $payrollGroup->compute_overbreak = false;
                $payrollGroup->compute_undertime = false;

                $payrollGroup->is_default = true;

                $payrollGroup->save();

                // Process
                GeneratePayrollDateAction::run($payrollGroup);

                // Create new Employee
                $employee = Employee::firstOrNew([
                    'company_id' => $company->id,
                    'employee_number' => '143',
                    'first_name' => 'Adrian',
                    'last_name' => 'Santos',
                ]);
                $employee->middle_name = 'D';

                $employee->position = 'HR Specialist';
                $employee->is_monday_friday = true;
                $employee->payment_type = Employee::PAYMENT_TYPE_MONTHLY;
                $employee->basic_salary = 75000;

                $employee->payroll_group_id = $payrollGroup->id;

                $employee->employment_type_id = 1;

                $employee->office_id = $office->id;
                $employee->department_id = $department->id;

                // $employee->schedule_id = $department->id;

                $employee->regular_holiday_pay = true;
                $employee->special_holiday_pay = true;
                $employee->premium_holiday_pay = true;
                $employee->regular_holiday_special_holiday_pay = true;
                $employee->rest_day_pay = true;
                $employee->overtime_pay = true;
                $employee->deminimis = true;
                $employee->night_differential = true;

                $employee->save();

                // Create default schedule
                $schedule = new Schedule;
                $schedule->company_id = $id;
                $schedule->name = 'Default 8am to 5pm';
                $schedule->am_in = '08:00';
                $schedule->break_time_start = '12:00';
                $schedule->break_time_end = '13:00';
                $schedule->pm_out = '17:00';
                $schedule->is_active = true;
                $schedule->is_default = true;
                $schedule->save();

                $rates = Rate::withoutTenant()->where('company_id', 1)->get();

                foreach ($rates as $rate) {
                    $newRate = $rate->replicate();
                    $newRate->company_id = $id;
                    $newRate->save();
                }

                // Employment types
                $employmentTypes = EmploymentType::withoutTenant()->where('company_id', 1)->get();

                foreach ($employmentTypes as $type) {
                    $newType = $type->replicate();
                    $newType->company_id = $id;
                    $newType->save();
                }

                // philhealth
                $philhealth = DeductionPhilHealth::withoutTenant()->where('company_id', 1)->get();

                foreach ($philhealth as $ph) {
                    $new = $ph->replicate();
                    $new->company_id = $id;
                    $new->save();
                }

                // hdmf
                $hdmf = DeductionPagibig::withoutTenant()->where('company_id', 1)->get();

                foreach ($hdmf as $pagibig) {
                    $new = $pagibig->replicate();
                    $new->company_id = $id;
                    $new->save();
                }

                // Get the first employee
                $user = User::query()->where('company_id', $id)->first();

                if ($user) {

                    // return (new AccountCreated($user))->render();
                    Mail::to($user)->send(new AccountCreated($user));
                }
            }
        }
    }

    public function login($tokenPassword)
    {
        $user = User::where('token_password', $tokenPassword)->first();

        if ($user != null) {
            // login the user
            Auth::login($user);

            return redirect(config('app.url').config('nova.path'));
        }

        abort(404);
    }
}
