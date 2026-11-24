<?php

namespace App\Services\Company;

use App\Actions\GeneratePayrollDateAction;
use App\Models\BankFileTemplate;
use App\Models\Company;
use App\Models\DeductionPagibig;
use App\Models\DeductionPhilHealth;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\Group;
use App\Models\Office;
use App\Models\PayrollGroup;
use App\Models\Rate;
use App\Models\User;
use App\Support\Installation\Installation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the tenant-level defaults a new company needs before it can run
 * payroll. Everything is written inside the new tenant context so the
 * BelongsToTenant guards accept the records.
 */
final class CompanyProvisioner
{
    public function provision(Company $company, User $owner): void
    {
        app(TenantContext::class)->run($company, function () use ($company, $owner): void {
            $group = $this->administratorGroup($company);

            $this->attachOwner($company, $owner, $group);
            $this->seedSettings($company);
            $this->seedOffice($company);
            $this->seedDepartment($company);
            $this->seedPayrollGroup($company);
            $this->cloneCompanyReferences($company);
            $this->seedBankFileTemplate($company);
        });
    }

    private function administratorGroup(Company $company): Group
    {
        $permissions = collect(config('admin.access_permissions', []))
            ->mapWithKeys(fn (string $permission): array => [$permission => true])
            ->all();

        $group = Group::query()
            ->where('company_id', $company->getKey())
            ->where('name', 'Administrator')
            ->first() ?? new Group;

        $group->company_id = $company->getKey();
        $group->name = 'Administrator';
        $group->active = true;
        $group->permissions = $permissions;
        $group->save();

        return $group;
    }

    private function attachOwner(Company $company, User $owner, Group $group): void
    {
        $owner->companies()->syncWithoutDetaching([
            $company->getKey() => [
                'is_owner' => true,
                'group_id' => $group->getKey(),
            ],
        ]);

        if ($owner->company_id === null) {
            $owner->forceFill(['company_id' => $company->getKey()])->save();
        }
    }

    private function seedSettings(Company $company): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $sectorDefaults = Installation::sectorPolicy()->defaults();

        $settings = [
            'company_name' => $company->name,
            'trade_name' => $company->trade_name,
            'type' => $company->type,
            'tin' => $company->tin,
            'rdo' => $company->rdo,
            'sss' => $company->sss,
            'phil_health' => $company->phil_health,
            'hdmf' => $company->hdmf,
            'maximum_pairing_range' => '20',
            'minimum_pairing_range' => '5',
            'night_shift_differential_start' => (string) ($sectorDefaults['night_shift_differential_start'] ?? '22:00'),
            'night_shift_differential_end' => (string) ($sectorDefaults['night_shift_differential_end'] ?? '06:00'),
            'days_year_no_weeked' => (string) PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
            'days_year_with_weeked' => (string) PayrollGroup::NUMBER_WORKING_DAYS_MONSAT,
            'daily_paid' => (string) PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
            'deduct_tax' => '1',
            'auto_compute_overtime' => '1',
            'minutes_qualified_overtime' => '30',
            'auto_compute_nt' => '1',
            'auto_absent_no_timesheet' => '0',
            'auto_employee_number' => '1',
            'display_logo_payslip' => '1',
            'sss_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'phil_health_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'hdmf_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'minutes_tardy' => '15',
            'minutes_tardy_am_only' => '0',
            'undertime_tardi' => '1',
            'allow_forty_hours' => '0',
            'dont_compute' => '0',
            'last_name_first_dtr' => '0',
            'print_office_head_in_dtr' => '1',
            'print_overtime_in_dtr' => '1',
            'show_employee_number_dtr' => '1',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['company_id' => $company->getKey(), 'key' => $key],
                ['value' => $value === null ? null : (string) $value],
            );
        }
    }

    private function seedOffice(Company $company): void
    {
        $office = Office::query()->firstOrNew([
            'company_id' => $company->getKey(),
            'name' => 'Head Office',
        ]);

        if (! $office->exists) {
            $office->code = 'HO';
            $office->location = 'internal';
            $office->disbursing_officer = '';
            $office->save();
        }
    }

    private function seedDepartment(Company $company): void
    {
        $department = Department::query()->firstOrNew([
            'company_id' => $company->getKey(),
            'name' => 'Human Resources',
        ]);

        if (! $department->exists) {
            $department->code = 'HR';
            $department->save();
        }
    }

    private function seedPayrollGroup(Company $company): void
    {
        $payrollGroup = PayrollGroup::query()->firstOrNew([
            'company_id' => $company->getKey(),
            'name' => 'Default Kinsenas Katapusan',
        ]);

        if ($payrollGroup->exists) {
            return;
        }

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

        GeneratePayrollDateAction::run($payrollGroup);
    }

    /**
     * Clone the rate/employment-type/deduction catalogues from the first
     * company that has them. A brand-new installation gets a minimal
     * employment-type catalogue instead.
     */
    private function cloneCompanyReferences(Company $company): void
    {
        $sourceId = Company::query()
            ->whereKeyNot($company->getKey())
            ->orderBy('id')
            ->value('id');

        if ($sourceId !== null) {
            $this->cloneRows(EmploymentType::class, (int) $sourceId, $company);
            $this->cloneRows(Rate::class, (int) $sourceId, $company);
            $this->cloneRows(DeductionPhilHealth::class, (int) $sourceId, $company);
            $this->cloneRows(DeductionPagibig::class, (int) $sourceId, $company);
        }

        if (EmploymentType::query()->where('company_id', $company->getKey())->doesntExist()) {
            foreach ([
                ['REG', 'Regular'],
                ['PROB', 'Probationary'],
                ['CONTRACT', 'Contractual'],
                ['PROJECT', 'Project-based'],
                ['CASUAL', 'Casual'],
            ] as [$code, $name]) {
                $type = new EmploymentType;
                $type->company_id = $company->getKey();
                $type->code = $code;
                $type->name = $name;
                $type->is_payroll = true;
                $type->save();
            }
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function cloneRows(string $model, int $sourceId, Company $company): void
    {
        $table = (new $model)->getTable();

        if (! Schema::hasTable($table)) {
            return;
        }

        if ($model::query()->where('company_id', $company->getKey())->exists()) {
            return;
        }

        $rows = $model::withoutTenant()
            ->where('company_id', $sourceId)
            ->get();

        foreach ($rows as $row) {
            $clone = $row->replicate();
            $clone->company_id = $company->getKey();
            $clone->save();
        }
    }

    private function seedBankFileTemplate(Company $company): void
    {
        if (! class_exists(BankFileTemplate::class) || ! Schema::hasTable('bank_file_templates')) {
            return;
        }

        BankFileTemplate::query()->firstOrCreate(
            ['company_id' => $company->getKey(), 'name' => 'Generic Bank Credit'],
            [
                'delimiter' => ',',
                'columns' => [
                    ['source' => 'employee_number', 'label' => 'Employee No'],
                    ['source' => 'account_name', 'label' => 'Account Name'],
                    ['source' => 'account_number', 'label' => 'Account Number'],
                    ['source' => 'bank_name', 'label' => 'Bank'],
                    ['source' => 'amount', 'label' => 'Amount'],
                ],
                'amount_in_centavos' => false,
                'date_format' => 'Ymd',
                'is_active' => true,
            ],
        );
    }
}
