<?php

use App\Services\Statutory\Philippines\Generic\GenericContributionService;

it('computes a percentage with a floor and a cap', function () {
    $component = [
        'calculation' => 'percentage',
        'base' => 'monthly_basic',
        'employee_rate' => 0.05,
        'employer_rate' => 0.05,
        'min_base' => 1000000,
        'max_base' => 2000000,
        'max_employee' => 90000,
        'max_employer' => 90000,
    ];

    $below = GenericContributionService::compute($component, 500000);
    $atCap = GenericContributionService::compute($component, 5000000);

    expect($below['employee'])->toBe(50000) // ₱10,000 floor × 5% = ₱500
        ->and($below['employer'])->toBe(50000)
        ->and($atCap['employee'])->toBe(90000)
        ->and($atCap['employer'])->toBe(90000);
});

it('applies a bracket amount plus a rate on the excess', function () {
    $component = [
        'calculation' => 'bracket',
        'base' => 'taxable',
        'rounding' => 'half_up',
        'bands' => [
            ['from' => 0, 'to' => 999999, 'base' => 0, 'employee_rate' => 0, 'employer_rate' => 0],
            ['from' => 1000000, 'to' => null, 'base' => 0, 'employee_rate' => 0.10, 'employer_rate' => 0],
        ],
    ];

    $result = GenericContributionService::compute($component, 1425000);

    expect($result['employee'])->toBe(42500)
        ->and($result['employer'])->toBe(0);
});

it('supports fixed amounts and the applies-to filter', function () {
    $employeeOnly = GenericContributionService::compute([
        'calculation' => 'fixed',
        'applies_to' => 'employee',
        'employee_amount' => 12500,
        'employer_amount' => 9900,
    ], 0);

    expect($employeeOnly['employee'])->toBe(12500)
        ->and($employeeOnly['employer'])->toBe(0);
});

it('divides monthly components across the cut-offs', function () {
    expect(GenericContributionService::divisor('every_pay', 'semi-monthly', 'last_pay'))->toBe(2)
        ->and(GenericContributionService::divisor('every_pay', 'monthly', 'last_pay'))->toBe(1)
        ->and(GenericContributionService::divisor('first_pay', 'semi-monthly', 'last_pay'))->toBe(0)
        ->and(GenericContributionService::divisor('first_pay', 'semi-monthly', 'first_pay'))->toBe(1);
});
