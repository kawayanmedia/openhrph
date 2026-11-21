<?php

/**
 * Sector profiles.
 *
 * This file declares *which* rules, catalogues, screens and defaults apply to a
 * Government or Private installation. It deliberately contains no statutory
 * rates or amounts: those live in the effective-dated `statutory_*` tables so a
 * historical payroll stays reproducible. Where an item is still awaiting a
 * primary source it is marked `active => false` and carries a `source` note.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Profile
    |--------------------------------------------------------------------------
    |
    | Used before the installer runs and when an installation has no sector.
    | The installer still asks the operator to choose; this only keeps the
    | application working between deployment and configuration.
    |
    */

    'default' => 'Private',

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    */

    'profiles' => [

        'Government' => [
            'label' => 'Government',
            'description' => 'National government agencies, local government units and GOCCs.',
            'company_type' => 'Government',
            'aliases' => ['government', 'gov', 'public', 'public sector', 'government-owned'],

            'statutory' => [
                'social_insurance' => 'gsis',
                'social_insurance_agency' => 'GSIS',
                'contribution_codes' => ['gsis', 'philhealth', 'pagibig', 'withholding_tax'],
                'excluded_codes' => ['sss'],
                'agency_labels' => [
                    'gsis' => 'GSIS',
                    'philhealth' => 'PhilHealth',
                    'pagibig' => 'Pag-IBIG',
                    'withholding_tax' => 'BIR',
                ],
                'loan_agencies' => ['GSIS', 'HDMF', 'Company'],
                'employee_number_field' => 'gsis_number',
                'employee_number_label' => 'GSIS BP Number',
                'required_identifiers' => ['tin_number', 'gsis_number', 'philhealth_number', 'hdmf_number'],
            ],

            'employment_types' => [
                ['code' => 'PERM', 'name' => 'Permanent'],
                ['code' => 'TEMP', 'name' => 'Temporary'],
                ['code' => 'CAS', 'name' => 'Casual'],
                ['code' => 'CONTRACT', 'name' => 'Contractual'],
                ['code' => 'JO', 'name' => 'Job Order'],
                ['code' => 'COS', 'name' => 'Contract of Service'],
            ],

            'leave' => [
                // CSC leave types are pending confirmation against the Omnibus
                // Rules on Leave (MC 41 s. 1998); they are configurable rows.
                'types' => [
                    ['code' => 'VL', 'name' => 'Vacation Leave', 'allow_per_year' => 15, 'deductible' => true, 'source' => 'CSC MC 41 s.1998 (verify)'],
                    ['code' => 'SL', 'name' => 'Sick Leave', 'allow_per_year' => 15, 'deductible' => true, 'source' => 'CSC MC 41 s.1998 (verify)'],
                    ['code' => 'SPL', 'name' => 'Special Privilege Leave', 'allow_per_year' => 3, 'deductible' => true, 'source' => 'CSC MC 41 s.1998 (verify)'],
                    ['code' => 'FL', 'name' => 'Forced Leave/Mandatory Leave', 'allow_per_year' => 5, 'deductible' => true, 'source' => 'CSC MC 41 s.1998 (verify)'],
                    ['code' => 'MON', 'name' => 'Monetization Leave', 'allow_per_year' => 0, 'deductible' => false, 'source' => 'CSC MC 41 s.1998 (verify)'],
                    ['code' => 'TERM', 'name' => 'Terminal Leave', 'allow_per_year' => 0, 'deductible' => false, 'source' => 'CSC rules (verify)'],
                    ['code' => 'STUDY', 'name' => 'Study Leave', 'allow_per_year' => 0, 'deductible' => false, 'source' => 'CSC rules (verify)'],
                    ['code' => 'CAL', 'name' => 'Calamity Leave', 'allow_per_year' => 0, 'deductible' => false, 'source' => 'CSC rules (verify)'],
                    ['code' => 'REHAB', 'name' => 'Rehabilitation Leave', 'allow_per_year' => 0, 'deductible' => false, 'source' => 'CSC rules (verify)'],
                    ['code' => 'ML', 'name' => 'Maternity Leave', 'allow_per_year' => 105, 'deductible' => false, 'source' => 'RA 11210'],
                    ['code' => 'PL', 'name' => 'Paternity Leave', 'allow_per_year' => 7, 'deductible' => false, 'source' => 'RA 8187'],
                    ['code' => 'SPL-SP', 'name' => 'Solo Parent Leave', 'allow_per_year' => 7, 'deductible' => false, 'source' => 'RA 11861'],
                    ['code' => 'VAWC', 'name' => 'VAWC Leave', 'allow_per_year' => 10, 'deductible' => false, 'source' => 'RA 9262 Sec. 43'],
                    ['code' => 'SLW', 'name' => 'Special Leave for Women', 'allow_per_year' => 60, 'deductible' => false, 'source' => 'RA 9710 Sec. 18'],
                    ['code' => 'ADOPT', 'name' => 'Adoption Leave', 'allow_per_year' => 0, 'deductible' => false, 'source' => 'RA 11642 Sec. 36 (maternity/paternity entitlement)'],
                ],
                'rules' => [
                    'service_incentive_leave' => false,
                    'vacation_days_per_year' => 15,
                    'sick_days_per_year' => 15,
                    'special_privilege_days' => 3,
                    'forced_leave_days' => 5,
                    'monetization_max_days' => 30,
                    'terminal_leave' => true,
                ],
            ],

            'compensation' => [
                // Allowances are assigned per employee; the catalogue only
                // creates the template rows. Amounts stay with the employee or
                // the annual GAA/circular, never in this file.
                'elements' => [
                    ['code' => 'PERA', 'name' => 'Personnel Economic Relief Allowance', 'group' => 'Allowance', 'tax_option' => 'fully_nontaxable', 'active' => false, 'source' => 'DBM NBC 599 (verify)'],
                    ['code' => 'RATA', 'name' => 'Representation and Transportation Allowance', 'group' => 'Allowance', 'tax_option' => 'fully_taxable', 'active' => false, 'source' => 'DBM NBC (verify)'],
                    ['code' => 'SUBSISTENCE ALLOWANCE', 'name' => 'Subsistence Allowance', 'group' => 'Allowance', 'tax_option' => 'fully_nontaxable', 'active' => true, 'source' => 'Existing catalogue'],
                    ['code' => 'LAUNDRY ALLOWANCE', 'name' => 'Laundry Allowance', 'group' => 'Allowance', 'tax_option' => 'fully_nontaxable', 'active' => false, 'source' => 'GAA/DBM circular (verify)'],
                    ['code' => 'HAZARD PAY', 'name' => 'Hazard Pay', 'group' => 'Compensation', 'tax_option' => 'fully_taxable', 'active' => true, 'source' => 'Existing catalogue'],
                ],
            ],

            'bonus' => [
                'scheme' => 'government',
                'label' => 'Government statutory bonuses',
                'uses_thirteenth_month_run' => true,
                'items' => [
                    ['code' => 'MID-YEAR BONUS', 'name' => 'Mid-Year Bonus', 'one_month_basic' => true, 'active' => true, 'source' => 'EO 201 s.2016; DBM BC 2017-2'],
                    ['code' => 'YEAR-END BONUS', 'name' => 'Year-End Bonus', 'one_month_basic' => true, 'active' => true, 'source' => 'EO 201 s.2016; DBM BC 2016-4'],
                    ['code' => 'CASH GIFT', 'name' => 'Cash Gift', 'one_month_basic' => false, 'active' => true, 'source' => 'DBM BC 2016-4 footnote (amount verify)'],
                    ['code' => 'SRI', 'name' => 'Service Recognition Incentive', 'one_month_basic' => false, 'active' => true, 'source' => 'AO 40 s.2025; DBM BC 2025-3'],
                    ['code' => 'CNA INCENTIVE', 'name' => 'CNA Incentive', 'one_month_basic' => false, 'active' => true, 'source' => 'DBM BC 2024-4 / 2025-2 / 2026-1'],
                    ['code' => 'PBB', 'name' => 'Performance-Based Bonus', 'one_month_basic' => false, 'active' => false, 'source' => 'DBM CL 2025-13 (verify)'],
                    ['code' => 'LOYALTY CASH GIFT', 'name' => 'Loyalty Cash Gift', 'one_month_basic' => false, 'active' => false, 'source' => 'DBM loyalty award rules (verify)'],
                ],
            ],

            'documents' => [
                ['code' => 'PDS', 'name' => 'Personal Data Sheet (CS Form 212)', 'required' => true, 'source' => 'CSC (verify exact edition)'],
                ['code' => 'SALN', 'name' => 'Statement of Assets, Liabilities and Net Worth', 'required' => true, 'source' => 'RA 6713'],
                ['code' => 'SERVICE_RECORD', 'name' => 'Service Record', 'required' => true, 'source' => 'CSC (verify)'],
                ['code' => 'APPOINTMENT', 'name' => 'Appointment', 'required' => true, 'source' => 'CSC (verify)'],
                ['code' => 'NOSA', 'name' => 'Notice of Salary Adjustment', 'required' => false, 'source' => 'DBM/CSC (verify)'],
                ['code' => 'PLANTILLA', 'name' => 'Plantilla Item', 'required' => false, 'source' => 'DBM (verify)'],
                ['code' => 'ELIGIBILITY', 'name' => 'CSC Eligibility', 'required' => false, 'source' => 'CSC (verify)'],
            ],

            'reports' => [
                'remittances' => ['gsis', 'philhealth', 'pagibig', 'bir'],
                'loan_remittances' => ['GSIS', 'HDMF'],
                'registers' => ['coa_payroll_register'],
                'statutory_forms' => ['bir_2316', 'bir_1601c'],
            ],

            'rules' => [
                'night_differential' => [
                    'start' => '18:00',
                    'end' => '06:00',
                    // The window is a configurable default until the CSC/DBM
                    // rule is confirmed; it must not be treated as settled law.
                    'source' => 'CSC/DBM rule pending verification',
                ],
                'thirteenth_month' => false,
                'service_incentive_leave' => false,
                'step_increment_years' => 3,
                'loyalty_award_years' => 10,
                'retirement' => 'gsis',
                'separation_pay' => false,
                'retirement_pay' => false,
                'terminal_leave' => true,
            ],

            'features' => [
                'plantilla' => true,
                'nosa' => true,
                'salary_grade' => true,
                'service_record' => true,
                'saln' => true,
                'pds' => true,
                'step_increment' => true,
                'loyalty_award' => true,
                'terminal_leave' => true,
                'leave_monetization' => true,
                'gsis' => true,
                'sss' => false,
                'thirteenth_month' => false,
                'service_incentive_leave' => false,
                'separation_pay' => false,
                'retirement_pay' => false,
            ],

            'menu' => [
                'show' => ['government', 'plantilla', 'salary-grade', 'nosa', 'service-record', 'pds', 'saln'],
                'hide' => ['thirteenth-month', 'service-incentive-leave', 'separation-pay'],
            ],

            'defaults' => [
                'night_shift_differential_start' => '18:00',
                'night_shift_differential_end' => '06:00',
                'deduct_tax' => true,
            ],
        ],

        'Private' => [
            'label' => 'Private',
            'description' => 'Private establishments, non-profits and NGOs covered by the Labor Code.',
            'company_type' => 'Private',
            'aliases' => ['private', 'priv', 'non-profit', 'nonprofit', 'non profit', 'ngo'],

            'statutory' => [
                'social_insurance' => 'sss',
                'social_insurance_agency' => 'SSS',
                'contribution_codes' => ['sss', 'philhealth', 'pagibig', 'withholding_tax'],
                'excluded_codes' => ['gsis'],
                'agency_labels' => [
                    'sss' => 'SSS',
                    'philhealth' => 'PhilHealth',
                    'pagibig' => 'Pag-IBIG',
                    'withholding_tax' => 'BIR',
                ],
                'loan_agencies' => ['SSS', 'HDMF', 'Company'],
                'employee_number_field' => 'sss_number',
                'employee_number_label' => 'SSS Number',
                'required_identifiers' => ['tin_number', 'sss_number', 'philhealth_number', 'hdmf_number'],
            ],

            'employment_types' => [
                ['code' => 'REG', 'name' => 'Regular'],
                ['code' => 'PROB', 'name' => 'Probationary'],
                ['code' => 'PROJ', 'name' => 'Project'],
                ['code' => 'SEASON', 'name' => 'Seasonal'],
                ['code' => 'FIXED', 'name' => 'Fixed-term'],
                ['code' => 'PART', 'name' => 'Part-time'],
            ],

            'leave' => [
                'types' => [
                    ['code' => 'SIL', 'name' => 'Service Incentive Leave', 'allow_per_year' => 5, 'deductible' => true, 'source' => 'Labor Code Art. 95'],
                    ['code' => 'ML', 'name' => 'Maternity Leave', 'allow_per_year' => 105, 'deductible' => false, 'source' => 'RA 11210'],
                    ['code' => 'PL', 'name' => 'Paternity Leave', 'allow_per_year' => 7, 'deductible' => false, 'source' => 'RA 8187'],
                    ['code' => 'SPL-SP', 'name' => 'Solo Parent Leave', 'allow_per_year' => 7, 'deductible' => false, 'source' => 'RA 11861'],
                    ['code' => 'VAWC', 'name' => 'VAWC Leave', 'allow_per_year' => 10, 'deductible' => false, 'source' => 'RA 9262 Sec. 43'],
                    ['code' => 'SLW', 'name' => 'Special Leave for Women', 'allow_per_year' => 60, 'deductible' => false, 'source' => 'RA 9710 Sec. 18'],
                    ['code' => 'ADOPT', 'name' => 'Adoption Leave', 'allow_per_year' => 0, 'deductible' => false, 'source' => 'RA 11642 Sec. 36 (maternity/paternity entitlement)'],
                    ['code' => 'VL', 'name' => 'Vacation Leave', 'allow_per_year' => 0, 'deductible' => true, 'source' => 'Company policy'],
                    ['code' => 'SL', 'name' => 'Sick Leave', 'allow_per_year' => 0, 'deductible' => true, 'source' => 'Company policy'],
                ],
                'rules' => [
                    'service_incentive_leave' => true,
                    'service_incentive_days' => 5,
                    'monetization_max_days' => 0,
                    'terminal_leave' => false,
                ],
            ],

            'compensation' => [
                'elements' => [
                    ['code' => '13TH MONTH PAY', 'name' => '13th Month Pay', 'group' => 'Bonus', 'tax_option' => 'fully_nontaxable', 'active' => true, 'source' => 'PD 851'],
                    ['code' => 'SEPARATION PAY (TAXABLE)', 'name' => 'Separation Pay (Taxable)', 'group' => 'Compensation', 'tax_option' => 'fully_taxable', 'active' => true, 'source' => 'Labor Code; RA 10963'],
                    ['code' => 'RETIREMENT PAY', 'name' => 'Retirement Pay', 'group' => 'Compensation', 'tax_option' => 'fully_nontaxable', 'active' => true, 'source' => 'RA 7641'],
                    ['code' => 'SERVICE CHARGE', 'name' => 'Service Charge', 'group' => 'Compensation', 'tax_option' => 'fully_taxable', 'active' => false, 'source' => 'Labor Code Art. 96 (verify)'],
                ],
            ],

            'bonus' => [
                'scheme' => 'private',
                'label' => '13th month and company bonuses',
                'uses_thirteenth_month_run' => true,
                'items' => [
                    ['code' => '13TH MONTH PAY', 'name' => '13th Month Pay', 'one_month_basic' => false, 'active' => true, 'source' => 'PD 851'],
                ],
            ],

            'documents' => [
                ['code' => 'CONTRACT', 'name' => 'Employment Contract', 'required' => true, 'source' => 'Company policy'],
                ['code' => '201_FILE', 'name' => '201 File', 'required' => true, 'source' => 'Company policy'],
                ['code' => 'BIR_2316', 'name' => 'BIR Form 2316', 'required' => true, 'source' => 'BIR'],
                ['code' => 'CLEARANCE', 'name' => 'NBI/Police Clearance', 'required' => false, 'source' => 'Company policy'],
                ['code' => 'SSS_NUMBER', 'name' => 'SSS Number', 'required' => true, 'source' => 'RA 11199'],
                ['code' => 'PHILHEALTH_NUMBER', 'name' => 'PhilHealth Number', 'required' => true, 'source' => 'RA 11223'],
                ['code' => 'HDMF_NUMBER', 'name' => 'Pag-IBIG Number', 'required' => true, 'source' => 'RA 9679'],
            ],

            'reports' => [
                'remittances' => ['sss', 'philhealth', 'pagibig', 'bir'],
                'loan_remittances' => ['SSS', 'HDMF'],
                'registers' => ['thirteenth_month', 'service_incentive_leave'],
                'statutory_forms' => ['bir_2316', 'bir_1601c'],
            ],

            'rules' => [
                'night_differential' => [
                    'start' => '22:00',
                    'end' => '06:00',
                    'source' => 'Labor Code Art. 86',
                ],
                'thirteenth_month' => true,
                'service_incentive_leave' => true,
                'step_increment_years' => 0,
                'loyalty_award_years' => 0,
                'retirement' => 'ra_7641',
                'separation_pay' => true,
                'retirement_pay' => true,
                'terminal_leave' => false,
            ],

            'features' => [
                'plantilla' => false,
                'nosa' => false,
                'salary_grade' => false,
                'service_record' => false,
                'saln' => false,
                'pds' => false,
                'step_increment' => false,
                'loyalty_award' => false,
                'terminal_leave' => false,
                'leave_monetization' => false,
                'gsis' => false,
                'sss' => true,
                'thirteenth_month' => true,
                'service_incentive_leave' => true,
                'separation_pay' => true,
                'retirement_pay' => true,
            ],

            'menu' => [
                'show' => ['thirteenth-month', 'service-incentive-leave', 'separation-pay'],
                'hide' => ['government', 'plantilla', 'salary-grade', 'nosa', 'service-record', 'pds', 'saln'],
            ],

            'defaults' => [
                'night_shift_differential_start' => '22:00',
                'night_shift_differential_end' => '06:00',
                'deduct_tax' => true,
            ],
        ],
    ],
];
