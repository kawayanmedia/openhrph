<?php

use App\Services\Attendance\Devices\AttendancePunchSink;
use App\Services\Attendance\Devices\EloquentDeviceRepository;
use KawayanMedia\Attendance\Devices\Transport\RatsZktecoClientFactory;

return [

    /*
    |--------------------------------------------------------------------------
    | Package bindings
    |--------------------------------------------------------------------------
    |
    | These are the only application classes the attendance package needs. The
    | package config supplies defaults for everything else; this file replaces
    | the null contract placeholders with real implementations.
    |
    */

    'contracts' => [
        'repository' => EloquentDeviceRepository::class,
        'sink' => AttendancePunchSink::class,
        'zkteco_client_factory' => RatsZktecoClientFactory::class,
    ],

];
