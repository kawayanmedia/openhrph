<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyBank;
use App\Models\Group;
use App\Models\Signatory;
use App\Models\User;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the demo tenant, its owner account and the tenant-level defaults
 * (settings, banks and signatories).
 *
 * The credentials are for local development and demos only.
 */
class CompanySeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $admin = $this->seedAdminUser();
        $company = $this->seedCompany($admin);

        $this->runForDemoTenant(function (Company $company) use ($admin): void {
            $group = $this->seedAdministratorGroup($company);

            $admin->forceFill([
                'company_id' => $company->getKey(),
                'group_id' => $group->getKey(),
                'owner' => true,
                'is_temporary_password' => false,
            ])->save();

            DB::table('company_user')->updateOrInsert(
                ['company_id' => $company->getKey(), 'user_id' => $admin->getKey()],
                [
                    'is_owner' => true,
                    'group_id' => $group->getKey(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->seedSettings($company);
            $this->seedBanks($company);
            $this->seedSignatories($company);
        });
    }

    private function seedAdminUser(): User
    {
        $admin = User::query()->where('email', 'admin@example.com')->first() ?? new User;

        $admin->forceFill([
            'name' => 'Admin User',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'owner' => true,
            'is_temporary_password' => false,
        ]);

        if (! $admin->exists) {
            $admin->account_id = 0;
        }

        $admin->save();

        return $admin;
    }

    private function seedCompany(User $admin): Company
    {
        $company = Company::query()->where('slug', self::DEMO_COMPANY_SLUG)->first() ?? new Company;

        $company->forceFill([
            'user_id' => $admin->getKey(),
            'name' => 'OpenHR PH Demo Company',
            'code' => 'DEMO',
            'trade_name' => 'OpenHR PH',
            'type' => 'Private',
            'email' => 'admin@example.com',
            'tel_number' => '(02) 8888-0000',
            'website' => 'https://example.com',
            'address_no' => '123',
            'street' => 'Sample Street',
            'zip_code' => 1000,
            'date_registered' => now()->startOfYear()->toDateString(),
            'formation' => 'Corporation',
            'line_business' => 'Human resources and payroll services',
            'industry' => 'NON-AGRICULTURE',
            'tin' => '000-000-000-000',
            'rdo' => '000',
            'sss' => '00-0000000-0',
            'phil_health' => '00-000000000-0',
            'hdmf' => '0000-0000-0000',
            'signatory_bir' => 'Maria Santos',
            'signatory_sss' => 'Maria Santos',
            'signatory_phic' => 'Maria Santos',
            'signatory_hdmf' => 'Maria Santos',
            'signatory_dole' => 'Maria Santos',
            'uuid' => (string) Str::uuid(),
            'slug' => self::DEMO_COMPANY_SLUG,
            'primary_color' => '#0f766e',
            'setup_done' => true,
            'is_subscription' => false,
        ])->save();

        return $company;
    }

    private function seedAdministratorGroup(Company $company): Group
    {
        $permissions = collect(config('admin.access_permissions', []))
            ->mapWithKeys(fn (string $permission): array => [$permission => true])
            ->all();

        return $this->upsertModel(
            Group::class,
            ['company_id' => $company->getKey(), 'name' => 'Administrator'],
            ['active' => true, 'permissions' => $permissions],
        );
    }

    private function seedSettings(Company $company): void
    {
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
            'night_shift_differential_start' => '22:00',
            'night_shift_differential_end' => '06:00',
            'days_year_no_weeked' => '261',
            'days_year_with_weeked' => '313',
            'daily_paid' => '261',
            'deduct_tax' => '1',
            'auto_compute_overtime' => '1',
            'minutes_qualified_overtime' => '30',
            'auto_compute_nt' => '1',
            'auto_absent_no_timesheet' => '0',
            'auto_employee_number' => '1',
            'display_logo_payslip' => '1',
            'sss_schedule' => 'every_pay',
            'phil_health_schedule' => 'every_pay',
            'hdmf_schedule' => 'every_pay',
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
                ['value' => (string) $value],
            );
        }
    }

    private function seedBanks(Company $company): void
    {
        $banks = [
            ['BDO Unibank', 'BDO', '000000000000'],
            ['Land Bank of the Philippines', 'LBP', '000000000000'],
        ];

        foreach ($banks as [$name, $code, $accountNumber]) {
            $this->upsertModel(
                CompanyBank::class,
                ['company_id' => $company->getKey(), 'name' => $name],
                ['code' => $code, 'account_number' => $accountNumber],
            );
        }
    }

    private function seedSignatories(Company $company): void
    {
        $signatories = [
            ['Maria Santos', '(02) 8888-0001', '0917-000-0001', 'maria.santos@example.com'],
            ['Juan Dela Cruz', '(02) 8888-0002', '0917-000-0002', 'juan.delacruz@example.com'],
        ];

        foreach ($signatories as [$name, $phone, $mobile, $email]) {
            $this->upsertModel(
                Signatory::class,
                ['company_id' => $company->getKey(), 'name' => $name],
                ['phone' => $phone, 'mobile' => $mobile, 'email' => $email],
            );
        }
    }
}
