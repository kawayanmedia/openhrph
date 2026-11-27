<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin URI Path
    |--------------------------------------------------------------------------
    |
    | The URI path where the Livewire admin panel will be accessible from.
    | This is kept separate from Nova (NOVA_PATH) so both can run side-by-side.
    |
    */

    'path' => env('ADMIN_PATH', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Resource Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace where converted Livewire admin resources live.
    |
    */

    'resources_namespace' => 'App\\Livewire\\Admin\\Resources',

    /*
    |--------------------------------------------------------------------------
    | Gate
    |--------------------------------------------------------------------------
    |
    | The ability name used to determine who may access the admin panel.
    | The gate is registered in AuthServiceProvider.
    |
    */

    'gate' => 'viewAdmin',

    /*
    |--------------------------------------------------------------------------
    | Admin Access
    |--------------------------------------------------------------------------
    |
    | A user may enter the admin when they own the company or when their group
    | grants at least one of these permissions. `allow_all` is an emergency
    | escape hatch (ADMIN_ALLOW_ALL=true) and is off by default.
    |
    */

    'allow_all' => (bool) env('ADMIN_ALLOW_ALL', false),

    'access_permissions' => [
        'Company Settings',
        'Offices',
        'Employees',
        'Users',
        'Departments',
        'Devices',
        'Holidays',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden Menu Items
    |--------------------------------------------------------------------------
    |
    | Comma separated list of menu keys hidden for this installation.
    |
    */

    'hidden_menu' => array_values(array_filter(explode(',', (string) env('HIDDEN_MENU', '')))),

    /*
    |--------------------------------------------------------------------------
    | Navigation Menu
    |--------------------------------------------------------------------------
    |
    | Mirrors the Nova main menu. Each section contains groups and items.
    | An item is either a dashboard, a resource (uri key) or a link.
    |
    | Supported item keys:
    |   - dashboard: string dashboard key (renders "/{path}/dashboards/{key}")
    |   - resource:  resource uri key (renders "/{path}/resources/{uri}")
    |   - link:      absolute path string
    |
    */

    'menu' => [
        ['type' => 'dashboard', 'key' => 'main', 'label' => 'Dashboard', 'icon' => 'chart-bar'],
        ['type' => 'link', 'url' => '/getting-started', 'label' => 'Getting Started', 'icon' => 'sparkles'],
        ['type' => 'link', 'url' => '/assistant', 'label' => 'AI Assistant', 'icon' => 'sparkles'],

        [
            'label' => 'Requests',
            'icon' => 'question-mark-circle',
            'items' => [
                ['type' => 'resource', 'key' => 'leave-request', 'can' => 'requests'],
                ['type' => 'resource', 'key' => 'attendance-request', 'can' => 'requests'],
                ['type' => 'resource', 'key' => 'schedule-adjustment-request', 'can' => 'requests'],
                ['type' => 'resource', 'key' => 'overtime-request', 'can' => 'requests'],
                ['type' => 'resource', 'key' => 'undertime-request', 'can' => 'requests'],
                ['type' => 'resource', 'key' => 'official-business-request', 'can' => 'requests'],
            ],
        ],

        [
            'label' => 'HR',
            'icon' => 'users',
            'can' => 'employees',
            'items' => [
                ['type' => 'link', 'url' => '/approvals', 'label' => 'Approvals'],
                ['type' => 'resource', 'key' => 'organization-unit', 'label' => 'Organization Units'],
                ['type' => 'resource', 'key' => 'location', 'label' => 'Locations'],
                ['type' => 'resource', 'key' => 'cost-center', 'label' => 'Cost Centers'],
                ['type' => 'resource', 'key' => 'position', 'label' => 'Positions'],
                ['type' => 'resource', 'key' => 'position-requirement', 'label' => 'Position Requirements'],
                ['type' => 'resource', 'key' => 'position-assignment', 'label' => 'Position Assignments'],
                ['type' => 'resource', 'key' => 'reporting-relationship', 'label' => 'Reporting Relationships'],
                ['type' => 'resource', 'key' => 'job-description', 'label' => 'Job Descriptions'],
                ['type' => 'resource', 'key' => 'employment-change', 'label' => 'Employment Changes'],
            ],
        ],

        [
            'label' => 'Documents',
            'icon' => 'document-text',
            'can' => 'employees',
            'items' => [
                ['type' => 'resource', 'key' => 'document', 'label' => 'Documents'],
            ],
        ],

        [
            'label' => 'Workflow',
            'icon' => 'adjustments',
            'can' => 'employees',
            'items' => [
                ['type' => 'resource', 'key' => 'workflow-definition', 'label' => 'Workflow Definitions'],
                ['type' => 'resource', 'key' => 'workflow-delegation', 'label' => 'Delegations'],
                ['type' => 'resource', 'key' => 'audit-event', 'label' => 'Audit Trail'],
            ],
        ],

        [
            'label' => 'Recruitment',
            'icon' => 'briefcase',
            'can' => 'employees',
            'sector' => 'Private',
            'items' => [
                ['type' => 'resource', 'key' => 'job-requisition', 'label' => 'Requisitions'],
                ['type' => 'resource', 'key' => 'job-vacancy', 'label' => 'Vacancies'],
                ['type' => 'resource', 'key' => 'applicant', 'label' => 'Applicants'],
                ['type' => 'resource', 'key' => 'application', 'label' => 'Applications'],
                ['type' => 'resource', 'key' => 'screening-result', 'label' => 'Screening'],
                ['type' => 'resource', 'key' => 'interview', 'label' => 'Interviews'],
                ['type' => 'resource', 'key' => 'interview-evaluation', 'label' => 'Evaluations'],
                ['type' => 'resource', 'key' => 'assessment', 'label' => 'Assessments'],
                ['type' => 'resource', 'key' => 'job-offer', 'label' => 'Offers'],
            ],
        ],

        [
            'label' => 'Employment',
            'icon' => 'identification',
            'can' => 'employees',
            'sector' => 'Private',
            'items' => [
                ['type' => 'resource', 'key' => 'employment', 'label' => 'Employments'],
                ['type' => 'resource', 'key' => 'employment-contract', 'label' => 'Contracts'],
                ['type' => 'resource', 'key' => 'probationary-review', 'label' => 'Probation Reviews'],
                ['type' => 'resource', 'key' => 'regularization-decision', 'label' => 'Regularization'],
                ['type' => 'resource', 'key' => 'onboarding-checklist', 'label' => 'Onboarding Checklists'],
                ['type' => 'resource', 'key' => 'onboarding-task', 'label' => 'Onboarding Tasks'],
                ['type' => 'resource', 'key' => 'leave-policy-rule', 'label' => 'Leave Policy Rules'],
            ],
        ],

        [
            'label' => 'Separation',
            'icon' => 'arrow-right-on-rectangle',
            'can' => 'employees',
            'sector' => 'Private',
            'items' => [
                ['type' => 'resource', 'key' => 'separation-case', 'label' => 'Separation Cases'],
                ['type' => 'resource', 'key' => 'clearance-template', 'label' => 'Clearance Templates'],
                ['type' => 'resource', 'key' => 'clearance-task', 'label' => 'Clearance Tasks'],
                ['type' => 'resource', 'key' => 'final-settlement', 'label' => 'Final Settlements'],
            ],
        ],

        [
            'label' => 'Reports',
            'icon' => 'document-text',
            'items' => [
                ['type' => 'link', 'url' => '/reports/remittances', 'label' => 'Government Remittances', 'can' => 'payroll_settings'],
                ['type' => 'link', 'url' => '/reports/employee-master-file', 'label' => 'Employee Master File', 'can' => 'employees'],
            ],
        ],

        [
            'label' => 'Settings',
            'icon' => 'adjustments',
            'groups' => [
                [
                    'label' => 'Company',
                    'items' => [
                        ['type' => 'link', 'url' => '/settings/general', 'label' => 'General Settings', 'can' => 'general_settings'],
                        ['type' => 'resource', 'key' => 'company', 'label' => 'Companies', 'can' => 'companies'],
                        ['type' => 'resource', 'key' => 'company-bank', 'label' => 'Payroll Accounts', 'can' => 'general_settings'],
                        ['type' => 'link', 'url' => '/imports', 'label' => 'Data Import'],
                        ['type' => 'link', 'url' => '/settings/sector', 'label' => 'Sector Settings', 'can' => 'general_settings'],
                        ['type' => 'resource', 'key' => 'office', 'label' => 'Office'],
                        ['type' => 'resource', 'key' => 'department', 'label' => 'Department'],
                        ['type' => 'resource', 'key' => 'announcement', 'label' => 'Announcement', 'can' => 'announcements'],
                        ['type' => 'resource', 'key' => 'job-vacancy', 'label' => 'Job Vacancy', 'can' => 'job_vacancies'],
                    ],
                ],
                [
                    'label' => 'Employee',
                    'items' => [
                        ['type' => 'resource', 'key' => 'employment-type', 'label' => 'Employment Type'],
                        ['type' => 'resource', 'key' => 'group', 'label' => 'Group', 'can' => 'groups'],
                        ['type' => 'resource', 'key' => 'employee', 'label' => 'Employee'],
                        ['type' => 'resource', 'key' => 'user', 'label' => 'User'],
                        ['type' => 'resource', 'key' => 'employee-certificate', 'label' => 'Employee Certificate', 'can' => 'employee_certificates'],
                        ['type' => 'resource', 'key' => 'employee-training', 'label' => 'Employee Training', 'can' => 'employee_trainings'],
                    ],
                ],
            ],
        ],

        [
            'label' => 'Time and Attendance',
            'icon' => 'clock',
            'can' => 'attendance_management',
            'groups' => [
                [
                    'label' => 'Attendance',
                    'items' => [
                        ['type' => 'link', 'url' => '/settings/attendance', 'label' => 'Attendance Settings', 'can' => 'attendance_settings'],
                        ['type' => 'resource', 'key' => 'device', 'label' => 'Device', 'can' => 'devices'],
                        ['type' => 'resource', 'key' => 'holiday', 'label' => 'Holiday'],
                        ['type' => 'resource', 'key' => 'rate', 'label' => 'Rate'],
                        ['type' => 'resource', 'key' => 'tardiness', 'label' => 'Tardiness', 'can' => 'tardiness'],
                        ['type' => 'resource', 'key' => 'schedule', 'label' => 'Schedule', 'can' => 'schedules'],
                    ],
                ],
            ],
            'trailing' => [
                ['type' => 'resource', 'key' => 'timesheet', 'label' => 'Timesheet'],
            ],
        ],

        [
            'label' => 'Leave Management',
            'icon' => 'calendar',
            'can' => 'leave_management',
            'items' => [
                ['type' => 'link', 'url' => '/settings/leave', 'label' => 'Leave Settings', 'can' => 'leave_settings'],
                ['type' => 'resource', 'key' => 'leave', 'label' => 'Leave'],
            ],
        ],

        [
            'label' => 'Payroll Management',
            'icon' => 'cash',
            'groups' => [
                [
                    'label' => 'Compensations',
                    'can' => 'compensations',
                    'items' => [
                        ['type' => 'resource', 'key' => 'compensation', 'label' => 'Compensation'],
                    ],
                ],
                [
                    'label' => 'Deductions',
                    'can' => 'deductions',
                    'items' => [
                        ['type' => 'resource', 'key' => 'deduction', 'label' => 'Deduction'],
                        ['type' => 'resource', 'key' => 'loan', 'label' => 'Loan'],
                    ],
                ],
            ],
            'items' => [
                ['type' => 'link', 'url' => '/settings/payroll', 'label' => 'Payroll Settings', 'can' => 'payroll_settings'],
                ['type' => 'resource', 'key' => 'bank-file-template', 'label' => 'Bank File Templates', 'can' => 'payroll_settings'],
                ['type' => 'resource', 'key' => 'pay-roll-group', 'label' => 'Payroll Group'],
                ['type' => 'resource', 'key' => 'pay-roll', 'label' => 'Payroll'],
                ['type' => 'resource', 'key' => 'employee-compensation', 'label' => 'Employee Compensation'],
                ['type' => 'resource', 'key' => 'employee-loan', 'label' => 'Employee Loan', 'can' => 'employee_loans'],
            ],
        ],
    ],

];
