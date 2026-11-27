<?php

use KawayanMedia\Attendance\Devices\Drivers\CsvExportDriver;
use KawayanMedia\Attendance\Devices\Drivers\DahuaCgiDriver;
use KawayanMedia\Attendance\Devices\Drivers\GenericJsonDriver;
use KawayanMedia\Attendance\Devices\Drivers\HikvisionIsapiDriver;
use KawayanMedia\Attendance\Devices\Drivers\SupremaBiostarDriver;
use KawayanMedia\Attendance\Devices\Drivers\ZktecoAdmsDriver;
use KawayanMedia\Attendance\Devices\Drivers\ZktecoUdpDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Push protocol prefix
    |--------------------------------------------------------------------------
    |
    | ADMS/iClock devices are configured with a server address only; they then
    | call `<server>/cdata`, `<server>/getrequest` and `<server>/devicecmd`.
    | The prefix keeps those routes out of the application's own URL space.
    |
    */

    'route_prefix' => env('ATTENDANCE_ADMS_PREFIX', 'iclock'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Maps the `devices.protocol` value to the class that speaks it. Any class
    | implementing `KawayanMedia\Attendance\Contracts\Devices\Driver` works, so
    | a vendor can be added without touching the sync service.
    |
    */

    'drivers' => [
        'generic_json' => GenericJsonDriver::class,
        'zkteco_adms' => ZktecoAdmsDriver::class,
        'zkteco_udp' => ZktecoUdpDriver::class,
        'hikvision_isapi' => HikvisionIsapiDriver::class,
        'dahua_cgi' => DahuaCgiDriver::class,
        'suprema_biostar' => SupremaBiostarDriver::class,
        'csv_export' => CsvExportDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Host bindings
    |--------------------------------------------------------------------------
    |
    | The package never touches Eloquent directly. The application supplies the
    | repository that resolves devices and keeps sync state, and the sink that
    | feeds punches into the timesheet recorder.
    |
    */

    'contracts' => [
        'repository' => null,
        'sink' => null,
        'zkteco_client_factory' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | ADMS handshake
    |--------------------------------------------------------------------------
    |
    | Values returned to a device that asks for its options. `TransTimes` is a
    | semicolon-separated list of HH:MM upload windows; `Delay` is the
    | keepalive interval in seconds.
    |
    */

    'adms' => [
        'delay' => (int) env('ATTENDANCE_ADMS_DELAY', 10),
        'error_delay' => (int) env('ATTENDANCE_ADMS_ERROR_DELAY', 30),
        'trans_interval' => (int) env('ATTENDANCE_ADMS_TRANS_INTERVAL', 1),
        'trans_times' => env('ATTENDANCE_ADMS_TRANS_TIMES', '00:00;12:00'),
        'realtime' => (int) env('ATTENDANCE_ADMS_REALTIME', 1),
        'encrypt' => (int) env('ATTENDANCE_ADMS_ENCRYPT', 0),
        'server_tz' => env('ATTENDANCE_ADMS_SERVER_TZ'),
        'push_key_required' => (bool) env('ATTENDANCE_ADMS_PUSH_KEY_REQUIRED', false),
        'throttle_per_minute' => (int) env('ATTENDANCE_ADMS_THROTTLE', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulling
    |--------------------------------------------------------------------------
    |
    | `overlap_minutes` re-reads a few minutes before the last cursor so a punch
    | stored out of order is not skipped; the punch ledger de-duplicates it.
    |
    */

    'pull' => [
        'overlap_minutes' => (int) env('ATTENDANCE_PULL_OVERLAP_MINUTES', 10),
        'history_days' => (int) env('ATTENDANCE_PULL_HISTORY_DAYS', 7),
        'batch_size' => (int) env('ATTENDANCE_PULL_BATCH_SIZE', 500),
        'max_records_per_run' => (int) env('ATTENDANCE_PULL_MAX_RECORDS', 5000),
        'timeout' => (int) env('ATTENDANCE_PULL_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | On-premise agent
    |--------------------------------------------------------------------------
    |
    | The agent authenticates with the company API key and fetches its device
    | list from the cloud application, so no device secret is stored on the
    | machine that sits in the office.
    |
    */

    'agent' => [
        'server' => env('ATTENDANCE_AGENT_SERVER'),
        'company_key' => env('ATTENDANCE_AGENT_COMPANY_KEY'),
        'interval' => (int) env('ATTENDANCE_AGENT_INTERVAL', 60),
        'batch_size' => (int) env('ATTENDANCE_AGENT_BATCH_SIZE', 500),
        'state_path' => env('ATTENDANCE_AGENT_STATE_PATH'),
        'timeout' => (int) env('ATTENDANCE_AGENT_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Device event retention
    |--------------------------------------------------------------------------
    */

    'events' => [
        'retention_days' => (int) env('ATTENDANCE_DEVICE_EVENT_RETENTION_DAYS', 30),
    ],
];
