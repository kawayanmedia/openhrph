<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The Go engine reads its statutory rates from these tables, so these tests
 * check the seeded rows against the published circulars rather than trusting
 * the importer. Expected values are transcribed from SSS Circular No.
 * 2024-006, RA 11223, RA 9679 / HDMF Circular 460 and RR 11-2018, and mirror
 * the cases asserted in payroll-engine/internal/statutory/sss_2025_test.go.
 */
/** Salary credit band covering a monthly salary, as stored in the database. */
function sssBandCoveringSalary(int $salaryCentavos): ?object
{
    return DB::table('statutory_sss_brackets')
        ->where('msc_from', '<=', $salaryCentavos)
        ->where(fn ($query) => $query->whereNull('msc_to')->orWhere('msc_to', '>=', $salaryCentavos))
        ->first();
}

it('seeds 61 contiguous SSS bands from the floor to the ceiling', function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    $bands = DB::table('statutory_sss_brackets')->orderBy('msc_from')->get();

    expect($bands)->toHaveCount(61)
        ->and($bands->first()->msc_from)->toBe(0)
        ->and($bands->first()->msc_to)->toBe(524999)
        ->and($bands->first()->msc)->toBe(500000)
        ->and($bands->last()->msc_from)->toBe(3475000)
        ->and($bands->last()->msc_to)->toBeNull()
        ->and($bands->last()->msc)->toBe(3500000);

    foreach ($bands->skip(1)->values() as $index => $band) {
        expect($band->msc_from)->toBe($bands[$index]->msc_to + 1);
    }
});

it('matches the salary credits and regular contributions in Circular 2024-006', function (int $salary, int $msc, int $ee, int $er, int $ec, int $wispEe, int $wispEr) {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    $band = sssBandCoveringSalary($salary * 100);

    expect($band)->not->toBeNull()
        ->and($band->msc)->toBe($msc * 100)
        ->and($band->ee)->toBe($ee * 100)
        ->and($band->er)->toBe($er * 100)
        ->and($band->ec)->toBe($ec * 100)
        ->and($band->wisp_ee)->toBe($wispEe * 100)
        ->and($band->wisp_er)->toBe($wispEr * 100);
})->with([
    // salary, MSC, regular employee, regular employer, EC, WISP employee, WISP employer
    'below the floor is billed at 5,000' => [1000, 5000, 250, 500, 10, 0, 0],
    'floor' => [5000, 5000, 250, 500, 10, 0, 0],
    'last centavo below the first step' => [5249, 5000, 250, 500, 10, 0, 0],
    'first centavo of the 5,500 band' => [5250, 5500, 275, 550, 10, 0, 0],
    'EC still 10 at 14,500' => [14500, 14500, 725, 1450, 10, 0, 0],
    'EC becomes 30 at 15,000' => [14750, 15000, 750, 1500, 30, 0, 0],
    'regular MSC cap 20,000' => [20000, 20000, 1000, 2000, 30, 0, 0],
    'WISP begins above 20,000' => [22500, 22500, 1000, 2000, 30, 125, 250],
    'ceiling band' => [35000, 35000, 1000, 2000, 30, 750, 1500],
    'above the ceiling stays at 35,000' => [100000, 35000, 1000, 2000, 30, 750, 1500],
]);

it('adds the regular and WISP shares to the totals printed in the circular', function (int $salary, int $employee, int $employer, int $total) {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    $band = sssBandCoveringSalary($salary * 100);

    // The circular prints one employee and one employer column per row; the
    // table splits each into its regular and MPF/WISP parts.
    expect($band->ee + $band->wisp_ee)->toBe($employee * 100)
        ->and($band->er + $band->wisp_er)->toBe($employer * 100)
        ->and($band->ee + $band->wisp_ee + $band->er + $band->wisp_er + $band->ec)->toBe($total * 100);
})->with([
    // salary, employee share, employer share, full remittance
    'floor' => [5000, 250, 500, 760],
    'at the regular MSC cap' => [20000, 1000, 2000, 3030],
    'WISP split at 22,500' => [22500, 1125, 2250, 3405],
    'at the ceiling' => [35000, 1750, 3500, 5280],
]);

it('seeds the PhilHealth, Pag-IBIG and tax schedules', function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    $philHealth = DB::table('statutory_philhealth')->first();
    $pagIbig = DB::table('statutory_pagibig')->first();

    expect((float) $philHealth->rate)->toBe(0.05)
        ->and($philHealth->floor_centavos)->toBe(1000000)
        ->and($philHealth->ceiling_centavos)->toBe(10000000)
        ->and((float) $pagIbig->rate)->toBe(0.02)
        ->and((float) $pagIbig->low_rate)->toBe(0.01)
        ->and($pagIbig->low_threshold_centavos)->toBe(150000)
        ->and($pagIbig->max_fund_salary_centavos)->toBe(1000000)
        ->and(DB::table('statutory_tax_brackets')->count())->toBe(12)
        ->and(DB::table('statutory_tax_brackets')->where('frequency', 'monthly')->count())->toBe(6)
        ->and(DB::table('statutory_tax_brackets')->where('frequency', 'semi-monthly')->count())->toBe(6);
});

it('seeds the BIR TRAIN band bases to the centavo', function (string $frequency, int $fromCentavos, int $baseTaxCentavos, float $rate) {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    $band = DB::table('statutory_tax_brackets')
        ->where('frequency', $frequency)
        ->where('from_centavos', $fromCentavos)
        ->first();

    expect($band)->not->toBeNull()
        ->and($band->base_tax_centavos)->toBe($baseTaxCentavos)
        ->and((float) $band->rate)->toBe($rate);
})->with([
    'monthly 20,833 band' => ['monthly', 2083300, 0, 0.15],
    'monthly 33,333 band' => ['monthly', 3333300, 187500, 0.20],
    'monthly 66,667 band' => ['monthly', 6666700, 854180, 0.25],
    'monthly 166,667 band' => ['monthly', 16666700, 3354180, 0.30],
    'monthly 666,667 band' => ['monthly', 66666700, 18354180, 0.35],
    'semi-monthly 10,417 band' => ['semi-monthly', 1041700, 0, 0.15],
    'semi-monthly 16,667 band' => ['semi-monthly', 1666700, 93750, 0.20],
    'semi-monthly 33,333 band' => ['semi-monthly', 3333300, 427070, 0.25],
    'semi-monthly 83,333 band' => ['semi-monthly', 8333300, 1677070, 0.30],
    'semi-monthly 333,333 band' => ['semi-monthly', 33333300, 9177070, 0.35],
]);

it('seeds the de minimis ceilings and the statutory sources', function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    expect(DB::table('statutory_de_minimis')->count())->toBe(6)
        ->and(DB::table('statutory_de_minimis')->where('code', 'rice')->value('ceiling_centavos'))->toBe(200000)
        ->and(DB::table('statutory_de_minimis')->where('code', 'clothing')->value('ceiling_centavos'))->toBe(600000)
        ->and(DB::table('statutory_de_minimis')->where('code', 'clothing')->value('basis'))->toBe('annual')
        ->and(DB::table('statutory_de_minimis')->where('code', 'medical')->value('ceiling_centavos'))->toBe(150000)
        ->and(DB::table('statutory_de_minimis')->where('code', 'medical')->value('basis'))->toBe('semester')
        ->and(DB::table('statutory_de_minimis')->where('code', 'meal')->value('basis'))->toBe('hourly_ot')
        ->and(DB::table('statutory_sources')->count())->toBe(5)
        ->and(DB::table('statutory_sources')->where('table_name', 'statutory_sss_brackets')->value('status'))->toBe('verified')
        ->and(DB::table('statutory_sources')->where('table_name', 'statutory_sss_brackets')->value('source_url'))
        ->toContain('sss.gov.ph');
});

it('is idempotent, so a redeploy does not duplicate the schedule', function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    expect(DB::table('statutory_sss_brackets')->count())->toBe(61)
        ->and(DB::table('statutory_tax_brackets')->count())->toBe(12)
        ->and(DB::table('statutory_de_minimis')->count())->toBe(6)
        ->and(DB::table('statutory_sources')->count())->toBe(5);
});

it('closes the predecessor schedule when a newer effectivity is imported', function () {
    config(['payroll.statutory.sss.effective_from' => '2025-01-01']);
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    config(['payroll.statutory.sss.effective_from' => '2026-01-01']);
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    // Both schedules are kept so historical payrolls stay reproducible.
    expect(DB::table('statutory_sss_brackets')->count())->toBe(122)
        ->and(DB::table('statutory_sss_brackets')->where('effective_from', '2025-01-01')->whereNull('effective_to')->count())->toBe(0)
        ->and(DB::table('statutory_sss_brackets')->where('effective_from', '2025-01-01')->where('effective_to', '2025-12-31')->count())->toBe(61)
        ->and(DB::table('statutory_sss_brackets')->where('effective_from', '2026-01-01')->whereNull('effective_to')->count())->toBe(61);
});

it('counts in whole centavos so no rate is lost to floating point', function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    $wisp = sssBandCoveringSalary(2250000);

    // 2,500.00 x 5% = 125.00 exactly, stored as 12,500 centavos.
    expect($wisp->wisp_ee)->toBe(12500)
        ->and($wisp->wisp_er)->toBe(25000)
        ->and($wisp->ee + $wisp->wisp_ee)->toBe(112500);
});
