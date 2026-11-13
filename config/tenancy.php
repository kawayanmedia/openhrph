<?php

use App\Models\Company;
use App\Models\SignupUser;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that represents a tenant in the application. Every
    | tenant-scoped table stores this model's primary key in the column
    | configured below.
    |
    */

    'tenant_model' => Company::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Foreign Key
    |--------------------------------------------------------------------------
    |
    | The column that links a tenant-scoped row to its company.
    |
    */

    'foreign_key' => 'company_id',

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, querying a tenant-scoped model without a resolved tenant
    | throws an exception instead of returning an empty result set. Disable
    | this only for legacy tooling; a missing tenant is always fail-closed.
    |
    */

    'strict' => env('TENANCY_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | The session key used to remember the company a user switched to.
    |
    */

    'session_key' => 'tenancy.company_id',

    /*
    |--------------------------------------------------------------------------
    | Platform Administrators
    |--------------------------------------------------------------------------
    |
    | E-mail addresses that may manage companies themselves. Company
    | management is not tenant-scoped, so it is limited to this list.
    |
    */

    'platform_admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('TENANCY_PLATFORM_ADMINS', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Public Hosts
    |--------------------------------------------------------------------------
    |
    | Optional host-to-company map used for unauthenticated pages such as the
    | public job board. Requests from an unmapped host fail closed.
    |
    | Example: ['careers.example.com' => 1]
    |
    */

    'public_hosts' => [],

    /*
    |--------------------------------------------------------------------------
    | Public Company
    |--------------------------------------------------------------------------
    |
    | Optional company id used for unauthenticated pages on the application's
    | own host (for example the public job board of the primary company).
    |
    */

    'public_company_id' => env('TENANCY_PUBLIC_COMPANY_ID'),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Tenant-aware cache keys are prefixed with this value followed by the
    | active company id. This prevents cross-tenant cache collisions.
    |
    */

    'cache_prefix' => 'tenant',

    /*
    |--------------------------------------------------------------------------
    | Global Tables And Models
    |--------------------------------------------------------------------------
    |
    | Tables that intentionally store rows without a company (statutory rate
    | tables, platform identities) and models that are deliberately global.
    |
    */

    'global_tables' => [
        'users',
        'nova_settings',
        'statutory_pagibig',
        'statutory_philhealth',
        'statutory_sss_brackets',
        'statutory_tax_brackets',
    ],

    'unscoped_models' => [
        User::class,
        SignupUser::class,
    ],

];
