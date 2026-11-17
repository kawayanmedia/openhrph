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
            'label' => 'Settings',
            'icon' => 'adjustments',
            'groups' => [
                [
                    'label' => 'Company',
                    'items' => [
                        ['type' => 'link', 'url' => '/settings/general', 'label' => 'General Settings', 'can' => 'general_settings'],
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
                ['type' => 'resource', 'key' => 'pay-roll-group', 'label' => 'Payroll Group'],
                ['type' => 'resource', 'key' => 'pay-roll', 'label' => 'Payroll'],
                ['type' => 'resource', 'key' => 'employee-compensation', 'label' => 'Employee Compensation'],
                ['type' => 'resource', 'key' => 'employee-loan', 'label' => 'Employee Loan', 'can' => 'employee_loans'],
                ['type' => 'resource', 'key' => 'pay-slip', 'label' => 'Pay Slip'],
            ],
        ],

        [
            'label' => 'Government',
            'icon' => 'office-building',
            'groups' => [
                [
                    'label' => 'Ref',
                    'items' => [
                        ['type' => 'resource', 'key' => 'phil-health', 'label' => 'PhilHealth'],
                        ['type' => 'resource', 'key' => 'sss', 'label' => 'SSS'],
                        ['type' => 'resource', 'key' => 'pagibig', 'label' => 'Pagibig'],
                        ['type' => 'resource', 'key' => 'minimum-wage-rate', 'label' => 'Minimum Wage Rate'],
                    ],
                ],
            ],
        ],
    ],

];
