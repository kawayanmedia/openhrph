<?php

use App\Providers\AppServiceProvider;
use App\Providers\DesktopServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    DesktopServiceProvider::class,
];
