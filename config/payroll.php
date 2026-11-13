<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payroll Engine
    |--------------------------------------------------------------------------
    |
    | Payroll runs are computed by the Go engine (bin/payroll-engine), which
    | reads MySQL directly and writes payroll_details in a single transaction.
    | The PHP engine remains available as a fallback for local debugging.
    |
    */

    'engine' => [
        'driver' => env('PAYROLL_ENGINE_DRIVER', 'go'),

        'binary' => env('PAYROLL_ENGINE_BINARY', base_path('bin/payroll-engine')),

        'timeout' => (int) env('PAYROLL_ENGINE_TIMEOUT', 600),

        'dsn' => env('PAYROLL_ENGINE_DSN'),

        // Payrolls up to this size are processed inline; larger ones are queued.
        'inline_threshold' => (int) env('PAYROLL_ENGINE_INLINE_THRESHOLD', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Statutory Defaults
    |--------------------------------------------------------------------------
    |
    | These values seed the versioned statutory tables. Once seeded, the
    | database is the source of truth and the Go engine reads from it.
    |
    */

    'statutory' => [
        'sss' => [
            'effective_from' => '2026-01-01',
            'employee_rate' => 0.05,
            'employer_rate' => 0.10,
            'msc_floor' => 5000,
            'msc_ceiling' => 35000,
            'msc_wisp_threshold' => 20000,
            'ec_employer' => 10,
            'ec_employer_high' => 30,
            'ec_high_msc' => 15000,
            'source' => 'https://www.sss.gov.ph/sss-contribution-table/',
        ],
        'philhealth' => [
            'effective_from' => '2026-01-01',
            'rate' => 0.05,
            'floor' => 10000,
            'ceiling' => 100000,
            'source' => 'https://www.philhealth.gov.ph/',
        ],
        'pagibig' => [
            'effective_from' => '2026-01-01',
            'rate' => 0.02,
            'low_rate' => 0.01,
            'low_threshold' => 1500,
            'max_fund_salary' => 10000,
            'source' => 'https://www.pagibigfund.gov.ph/',
        ],
        'tax' => [
            'effective_from' => '2026-01-01',
            'source' => 'https://www.bir.gov.ph/withholding-tax',
        ],
        // RR 2-98 de minimis ceilings as amended by RR 11-2018. The basis
        // controls how the ceiling is applied: monthly, annual, semester, or
        // 25% of the basic hourly rate for overtime/night meal allowances.
        // The 2026 rows are seeded as provisional until the CPA confirms the
        // current ceilings.
        'de_minimis' => [
            'effective_from' => '2026-01-01',
            'source' => 'https://www.bir.gov.ph/',
            'benefits' => [
                ['code' => 'rice', 'name' => 'Rice subsidy', 'ceiling' => 2000, 'basis' => 'monthly'],
                ['code' => 'clothing', 'name' => 'Uniform and clothing allowance', 'ceiling' => 6000, 'basis' => 'annual'],
                ['code' => 'medical', 'name' => 'Medical cash allowance to dependents', 'ceiling' => 1500, 'basis' => 'semester'],
                ['code' => 'laundry', 'name' => 'Laundry allowance', 'ceiling' => 300, 'basis' => 'monthly'],
                ['code' => 'meal', 'name' => 'Meal allowance', 'ceiling' => 0, 'basis' => 'hourly_ot'],
                ['code' => 'medical_assistance', 'name' => 'Medical assistance', 'ceiling' => 10000, 'basis' => 'annual'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payroll Accounting
    |--------------------------------------------------------------------------
    |
    | Account codes used when a processed payroll is approved. Change these to
    | match the company's chart of accounts.
    |
    */

    'accounting' => [
        'accounts' => [
            'salaries_expense' => env('PAYROLL_ACCOUNT_SALARIES', '5100'),
            'employer_contributions_expense' => env('PAYROLL_ACCOUNT_EMPLOYER_CONTRIB', '5110'),
            'withholding_tax_payable' => env('PAYROLL_ACCOUNT_TAX_PAYABLE', '2100'),
            'sss_payable' => env('PAYROLL_ACCOUNT_SSS_PAYABLE', '2110'),
            'philhealth_payable' => env('PAYROLL_ACCOUNT_PHILHEALTH_PAYABLE', '2120'),
            'pagibig_payable' => env('PAYROLL_ACCOUNT_PAGIBIG_PAYABLE', '2130'),
            'other_deductions_payable' => env('PAYROLL_ACCOUNT_OTHER_DEDUCTIONS', '2140'),
            'net_pay_payable' => env('PAYROLL_ACCOUNT_NET_PAY_PAYABLE', '2200'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Fixtures
    |--------------------------------------------------------------------------
    |
    | Used by the browser test suite to create (and purge) an isolated company
    | so end-to-end runs never touch real payroll data.
    |
    */

    'e2e' => [
        'company_prefix' => 'E2E ',
        'user_email' => 'e2e@hris.test',
        'password' => env('E2E_PASSWORD', 'E2ePassword!2345'),
    ],

];
