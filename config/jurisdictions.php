<?php

use App\Jurisdictions\Philippines\PhilippinesJurisdiction;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Jurisdiction
    |--------------------------------------------------------------------------
    |
    | Used before the installer runs. The Philippines stays the default so an
    | unconfigured installation behaves exactly as it did.
    |
    */

    'default' => env('JURISDICTION_DEFAULT', 'PH'),

    /*
    |--------------------------------------------------------------------------
    | Registered Country Plugins
    |--------------------------------------------------------------------------
    |
    | Modules under app/Jurisdictions are discovered automatically. List a
    | third-party jurisdiction here when it lives in a Composer package, or
    | register it from that package's service provider.
    |
    */

    'register' => [
        PhilippinesJurisdiction::class,
    ],

];
