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
        // RA 8291 Sec. 5(a): 9% personal and 12% government share on the actual
        // monthly compensation, with no MSC brackets. The Maximum AMC limit is
        // zero because GSIS currently assesses the full compensation at 9%.
        'gsis' => [
            'effective_from' => '1997-06-20',
            'employee_rate' => 0.09,
            'employer_rate' => 0.12,
            'max_amc_limit' => 0,
            'above_limit_employee_rate' => 0.02,
            'source' => 'https://lawphil.net/statutes/repacts/ra1997/ra_8291_1997.html',
        ],
        'tax' => [
            'effective_from' => '2026-01-01',
            'source' => 'https://www.bir.gov.ph/withholding-tax',
        ],
        // BIR RR 2-98 Sec. 2.78.1 de minimis ceilings as amended by RR 29-2025.
        // The basis controls how the ceiling is applied: monthly, annual,
        // semester, or a percentage of the regional minimum wage per overtime
        // hour for the meal allowance.
        'de_minimis' => [
            'effective_from' => '2026-01-01',
            'source' => 'https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%2029-2025%20digest%20FINAL.pdf',
            'benefits' => [
                ['code' => 'rice', 'name' => 'Rice subsidy', 'ceiling' => 2500, 'basis' => 'monthly'],
                ['code' => 'clothing', 'name' => 'Uniform and clothing allowance', 'ceiling' => 8000, 'basis' => 'annual'],
                ['code' => 'medical', 'name' => 'Medical cash allowance to dependents', 'ceiling' => 2000, 'basis' => 'semester'],
                ['code' => 'laundry', 'name' => 'Laundry allowance', 'ceiling' => 400, 'basis' => 'monthly'],
                ['code' => 'meal', 'name' => 'Meal allowance for overtime and night shift', 'ceiling' => 0, 'basis' => 'hourly_ot', 'percentage' => 30],
                ['code' => 'medical_assistance', 'name' => 'Actual medical assistance', 'ceiling' => 12000, 'basis' => 'annual'],
                ['code' => 'achievement_award', 'name' => 'Employee achievement awards', 'ceiling' => 12000, 'basis' => 'annual'],
                ['code' => 'gifts', 'name' => 'Christmas and major anniversary gifts', 'ceiling' => 6000, 'basis' => 'annual'],
                ['code' => 'cba_productivity', 'name' => 'CBA and productivity incentive benefits', 'ceiling' => 12000, 'basis' => 'annual'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Country Profiles
    |--------------------------------------------------------------------------
    |
    | One country per installation. `PH` uses the built-in Philippine driver and
    | the versioned statutory tables above. Every other country uses the generic
    | driver, which reads `statutory_components` and `statutory_component_bands`
    | so a new jurisdiction can be configured without code changes.
    |
    | `components` below documents the labels and behaviour an installation
    | expects. The PH rates stay in the statutory tables; the list is mirrored
    | into `statutory_components` so the admin catalogue is consistent.
    |
    */

    'default_country' => env('PAYROLL_COUNTRY', 'PH'),

    'countries' => [
        'PH' => [
            'name' => 'Philippines',
            'currency' => 'PHP',
            'currency_symbol' => '₱',
            'locale' => 'en_PH',
            'timezone' => 'Asia/Manila',
            'statutory_driver' => 'ph',
            'minimum_wage_region' => 'NCR',
            'components' => [
                ['code' => 'sss', 'name' => 'SSS', 'category' => 'contribution', 'applies_to' => 'both', 'calculation' => 'bracket', 'base' => 'monthly_basic', 'reduces_taxable_income' => true, 'sort_order' => 10, 'managed_by' => 'ph'],
                ['code' => 'philhealth', 'name' => 'PhilHealth', 'category' => 'contribution', 'applies_to' => 'both', 'calculation' => 'percentage', 'base' => 'monthly_basic', 'reduces_taxable_income' => true, 'sort_order' => 20, 'managed_by' => 'ph'],
                ['code' => 'pagibig', 'name' => 'Pag-IBIG', 'category' => 'fund', 'applies_to' => 'both', 'calculation' => 'percentage', 'base' => 'monthly_basic', 'reduces_taxable_income' => true, 'sort_order' => 30, 'managed_by' => 'ph'],
                ['code' => 'gsis', 'name' => 'GSIS', 'category' => 'contribution', 'applies_to' => 'both', 'calculation' => 'percentage', 'base' => 'monthly_basic', 'reduces_taxable_income' => true, 'sort_order' => 40, 'sector' => 'government', 'managed_by' => 'ph'],
                ['code' => 'withholding_tax', 'name' => 'Withholding tax', 'category' => 'tax', 'applies_to' => 'employee', 'calculation' => 'bracket', 'base' => 'taxable', 'reduces_taxable_income' => false, 'sort_order' => 900, 'managed_by' => 'ph'],
            ],
            'deductions' => [
                ['code' => 'union_dues', 'name' => 'Union dues', 'type' => 'voluntary', 'calculation' => 'fixed', 'reduces_taxable_income' => false, 'sort_order' => 10],
                ['code' => 'coop_savings', 'name' => 'Cooperative savings', 'type' => 'voluntary', 'calculation' => 'percentage', 'base' => 'period_basic', 'reduces_taxable_income' => false, 'sort_order' => 20],
                ['code' => 'cash_advance', 'name' => 'Cash advance', 'type' => 'voluntary', 'calculation' => 'fixed', 'reduces_taxable_income' => false, 'sort_order' => 30],
                ['code' => 'other', 'name' => 'Other deduction', 'type' => 'voluntary', 'calculation' => 'fixed', 'reduces_taxable_income' => false, 'sort_order' => 900],
            ],
            // Loan programs offered by each agency. `sector` limits a program
            // to government or private organisations; a missing sector applies
            // to both. Loans are amortized through employees_loans and are not
            // part of the statutory contribution tables.
            'loans' => [
                ['agency' => 'SSS', 'code' => 'SSS-SALARY', 'name' => 'SSS Salary Loan', 'sector' => 'private'],
                ['agency' => 'SSS', 'code' => 'SSS-EMERGENCY', 'name' => 'SSS Emergency Loan', 'sector' => 'private'],
                ['agency' => 'HDMF', 'code' => 'HDMF-MPL', 'name' => 'Pag-IBIG Multi-Purpose Loan'],
                ['agency' => 'HDMF', 'code' => 'HDMF-CALAMITY', 'name' => 'Pag-IBIG Calamity Loan'],
                ['agency' => 'GSIS', 'code' => 'GSIS-POLICY', 'name' => 'GSIS Policy Loan', 'sector' => 'government'],
                ['agency' => 'GSIS', 'code' => 'GSIS-EMERGENCY', 'name' => 'GSIS Emergency Loan', 'sector' => 'government'],
                ['agency' => 'GSIS', 'code' => 'GSIS-REAL-ESTATE', 'name' => 'GSIS Real Estate Loan', 'sector' => 'government'],
                ['agency' => 'Company', 'code' => 'COMPANY-LOAN', 'name' => 'Company Loan'],
            ],
        ],

        // Add one entry per supported jurisdiction. Rates and bands are never
        // stored here: they are imported into the effective-dated statutory
        // tables so historical runs stay reproducible. See
        // docs/developer-guide/payroll-engine.md for the country contract.
    ],

    /*
    |--------------------------------------------------------------------------
    | Statutory Drivers
    |--------------------------------------------------------------------------
    |
    | The driver decides how a payroll run computes statutory deductions. A
    | country that cannot be expressed with the generic component tables can add
    | a dedicated driver class on both the PHP and Go side while keeping the
    | same configuration contract.
    |
    */

    'statutory_drivers' => ['ph', 'generic'],

    /*
    |--------------------------------------------------------------------------
    | Remittance Register
    |--------------------------------------------------------------------------
    |
    | Default filing/payment deadlines, expressed as an offset in days from the
    | payroll date. These are editable working defaults, not legal advice.
    |
    */

    'remittances' => [
        'agencies' => [
            'sss' => 'SSS',
            'gsis' => 'GSIS',
            'philhealth' => 'PhilHealth',
            'pagibig' => 'Pag-IBIG',
            'bir' => 'BIR',
        ],
        'due_days' => [
            'sss' => 15,
            'gsis' => 10,
            'philhealth' => 15,
            'pagibig' => 15,
            'bir' => 10,
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
            'gsis_payable' => env('PAYROLL_ACCOUNT_GSIS_PAYABLE', '2115'),
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
        'onboarding_user_email' => 'e2e-onboarding@hris.test',
        'onboarding_password' => env('E2E_ONBOARDING_PASSWORD', 'E2ePassword!2345'),

        // The attendance journey tenant: one owner and three portal employees
        // working a day, night and split shift through November.
        'attendance_user_email' => 'e2e-attendance@hris.test',
        'attendance_password' => env('E2E_ATTENDANCE_PASSWORD', 'E2ePassword!2345'),
        'attendance_employee_emails' => [
            'day' => 'e2e-day@hris.test',
            'night' => 'e2e-night@hris.test',
            'split' => 'e2e-split@hris.test',
        ],
        'attendance_month' => 11,
    ],

];
