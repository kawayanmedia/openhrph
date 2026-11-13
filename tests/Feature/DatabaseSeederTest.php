<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        'salary_grades',
        'plantilla_items',
        'allowances',
        'bonuses',
        'commissions',
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

        $admin = DB::table('users')->where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame(1, (int) $admin->owner);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame('demo', DB::table('companies')->where('id', $admin->company_id)->value('slug'));
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
