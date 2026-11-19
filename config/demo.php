<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Demo
    |--------------------------------------------------------------------------
    |
    | When enabled, visitors can create an account, verify their email address
    | and use an isolated demo company. Leave this disabled for installations
    | that hold real payroll data: public registration is only meant for the
    | hosted demo.
    |
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Demo URL
    |--------------------------------------------------------------------------
    |
    | The hosted demo linked from the public landing page and the docs. It is
    | kept in config so non-demo deployments can point at the same demo.
    |
    */

    'url' => env('DEMO_URL', 'https://openhrph.laravel.cloud'),

    /*
    |--------------------------------------------------------------------------
    | Registration Throttle
    |--------------------------------------------------------------------------
    |
    | Maximum demo accounts one IP address may create per hour.
    |
    */

    'registrations_per_hour' => (int) env('DEMO_REGISTRATIONS_PER_HOUR', 5),

];
