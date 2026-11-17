<?php

use App\Services\Payroll\Statutory\ComponentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payroll.countries.XX', [
        'name' => 'Testland',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'locale' => 'en',
        'timezone' => 'UTC',
        'statutory_driver' => 'generic',
        'components' => [],
        'deductions' => [],
    ]);

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'XX',
        '--skip-statutory' => true,
    ])->assertExitCode(0);
});

it('imports a generic country component definition', function () {
    $path = sys_get_temp_dir().'/openhr-components-'.uniqid().'.json';

    file_put_contents($path, json_encode([
        'effective_from' => '2026-01-01',
        'source_url' => 'https://example.test/statutory',
        'components' => [
            [
                'code' => 'social',
                'name' => 'Social insurance',
                'category' => 'contribution',
                'applies_to' => 'both',
                'calculation' => 'percentage',
                'base' => 'monthly_basic',
                'employee_rate' => 0.05,
                'employer_rate' => 0.05,
                'reduces_taxable_income' => true,
                'sort_order' => 10,
                'effective_from' => '2026-01-01',
            ],
            [
                'code' => 'income_tax',
                'name' => 'Income tax',
                'category' => 'tax',
                'applies_to' => 'employee',
                'calculation' => 'bracket',
                'base' => 'taxable',
                'sort_order' => 900,
                'effective_from' => '2026-01-01',
                'bands' => [
                    ['from_centavos' => 0, 'to_centavos' => 999999, 'employee_rate' => 0],
                    ['from_centavos' => 1000000, 'to_centavos' => null, 'employee_rate' => 0.10],
                ],
            ],
        ],
    ]));

    try {
        $this->artisan('payroll:statutory:import', ['--country' => 'XX', '--file' => $path])->assertExitCode(0);

        expect(DB::table('statutory_components')->where('country_code', 'XX')->count())->toBe(2)
            ->and(DB::table('statutory_component_bands')->count())->toBe(2)
            ->and(DB::table('statutory_sources')->where('country_code', 'XX')->exists())->toBeTrue();

        $definitions = ComponentResolver::definitions(9, 'XX', 'private', '2026-03-15');

        expect($definitions)->toHaveCount(2)
            ->and($definitions[0]['code'])->toBe('social')
            ->and($definitions[0]['employee_rate'])->toBe(0.05)
            ->and($definitions[1]['bands'])->toHaveCount(2);

        $this->artisan('payroll:statutory:verify', ['date' => '2026-03-15', '--country' => 'XX'])
            ->assertExitCode(0);
    } finally {
        @unlink($path);
    }
});

it('rejects a bracket component without bands', function () {
    $path = sys_get_temp_dir().'/openhr-components-'.uniqid().'.json';

    file_put_contents($path, json_encode([
        'components' => [
            [
                'code' => 'income_tax',
                'name' => 'Income tax',
                'category' => 'tax',
                'calculation' => 'bracket',
                'base' => 'taxable',
            ],
        ],
    ]));

    try {
        $this->artisan('payroll:statutory:import', ['--country' => 'XX', '--file' => $path])->assertExitCode(1);

        expect(DB::table('statutory_components')->where('country_code', 'XX')->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});
