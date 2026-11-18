<?php

namespace Tests\Feature;

use App\Support\Installation\Installation;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tables that must contain baseline data after `db:seed`.
     *
     * @var array<int, string>
     */
    private const SEEDED_TABLES = [
        'companies',
        'users',
        'groups',
        'company_user',
        'settings',
        'companies_banks',
        'signatories',
        'offices',
        'departments',
        'positions',
        'employment_types',
        'schedules',
        'tardiness',
        'payroll_groups',
        'leave_types',
        'holidays',
        'plantilla_items',
        'deductions',
        'loans',
        'compensations',
        'rates',
        'statutory_sss_brackets',
        'statutory_tax_brackets',
        'statutory_philhealth',
        'statutory_pagibig',
        'statutory_de_minimis',
        'statutory_min_wage',
        'statutory_sources',
    ];

    public function test_database_seeder_populates_the_reference_data(): void
    {
        $this->seed();

        foreach (self::SEEDED_TABLES as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), "Table [{$table}] was not seeded.");
        }

        foreach (['Allowance', 'Bonus', 'Commission', 'Compensation'] as $group) {
            $this->assertGreaterThan(
                0,
                DB::table('compensations')->where('group', $group)->count(),
                "Compensation group [{$group}] was not seeded.",
            );
        }

        $codes = DB::table('compensations')->whereNull('deleted_at')->pluck('code');

        $this->assertSame($codes->count(), $codes->unique()->count(), 'Compensation codes must be unique.');
        $this->assertSame(0, DB::table('compensations')->whereNotNull('company_id')->count(), 'Compensations must be system-wide.');

        foreach (['allowances', 'bonuses', 'commissions', 'employees_allowances', 'employees_bonuses', 'employees_commissions'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Legacy table [{$table}] should have been removed.");
        }

        $admin = DB::table('users')->where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame(1, (int) $admin->owner);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame('demo', DB::table('companies')->where('id', $admin->company_id)->value('slug'));
    }

    public function test_database_seeder_seeds_government_reference_data_for_a_government_installation(): void
    {
        Installation::set(Installation::KEY_SECTOR, Installation::SECTOR_GOVERNMENT);

        $this->seed();

        $this->assertGreaterThan(0, DB::table('salary_grades')->count(), 'Salary grades were not seeded.');
        $this->assertGreaterThan(0, DB::table('plantilla_items')->count(), 'Plantilla items were not seeded.');
        $this->assertSame(0, DB::table('leave_types')->whereNotNull('company_id')->count(), 'Leave types must be system-wide.');
    }

    public function test_database_seeder_skips_government_reference_data_for_a_private_installation(): void
    {
        Installation::set(Installation::KEY_SECTOR, Installation::SECTOR_PRIVATE);

        $this->seed();

        $this->assertSame(0, DB::table('salary_grades')->count(), 'Salary grades must not be seeded for a private installation.');
        $this->assertSame(0, DB::table('plantilla_items')->count(), 'Plantilla items must not be seeded for a private installation.');
        $this->assertGreaterThan(0, DB::table('leave_types')->whereNull('company_id')->count(), 'Leave types were not seeded.');
    }

    public function test_reference_data_seeder_seeds_catalogues_without_a_demo_tenant(): void
    {
        DB::table('leave_types')->delete();

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame(0, DB::table('companies')->count(), 'The reference seeder must not create a demo tenant.');
        $this->assertGreaterThan(0, DB::table('leave_types')->whereNull('company_id')->count(), 'Leave types were not seeded.');
        $this->assertGreaterThan(0, DB::table('compensations')->whereNull('company_id')->count(), 'Compensations were not seeded.');
    }

    public function test_database_seeder_can_run_more_than_once_without_duplicating_rows(): void
    {
        $this->seed();
        $firstRun = $this->tableCounts();

        $this->seed();

        $this->assertSame($firstRun, $this->tableCounts());
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        return collect(self::SEEDED_TABLES)
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
