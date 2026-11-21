<?php

use App\Jurisdictions\Support\JurisdictionServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DesktopServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    JurisdictionServiceProvider::class,
    TenancyServiceProvider::class,
    DesktopServiceProvider::class,
];
