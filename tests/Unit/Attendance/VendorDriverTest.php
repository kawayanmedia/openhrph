<?php

use Illuminate\Support\Facades\Http;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\Drivers\DahuaCgiDriver;
use KawayanMedia\Attendance\Devices\Drivers\HikvisionIsapiDriver;
use KawayanMedia\Attendance\Devices\Drivers\SupremaBiostarDriver;
use KawayanMedia\Attendance\Devices\PullWindow;

function vendorDevice(string $protocol, array $overrides = []): DeviceDescriptor
{
    return new DeviceDescriptor(
        id: 1,
        tenantId: 1,
        name: 'Terminal',
        protocol: $protocol,
        host: '192.168.1.50',
        port: $overrides['port'] ?? 80,
        timezone: 'Asia/Manila',
        credentials: $overrides['credentials'] ?? ['username' => 'admin', 'password' => 'secret'],
        config: $overrides['config'] ?? [],
    );
}

it('pulls Hikvision access events after answering the digest challenge', function () {
    $time = now()->subMinutes(5)->setTimezone('Asia/Manila')->toIso8601String();

    Http::fake([
        '*/ISAPI/AccessControl/AcsEvent*' => Http::sequence()
            ->push('', 401, ['WWW-Authenticate' => 'Digest realm="IP Camera", nonce="abc123", qop="auth"'])
            ->push([
                'AcsEvent' => [
                    'responseStatusStrg' => 'OK',
                    'InfoList' => [[
                        'employeeNoString' => '101',
                        'time' => $time,
                        'inAndOutType' => 1,
                        'serialNo' => 12,
                    ]],
                ],
            ], 200),
    ]);

    $device = vendorDevice('hikvision_isapi');
    $result = (new HikvisionIsapiDriver)->pull($device, PullWindow::forDevice($device));

    expect($result->records)->toHaveCount(1)
        ->and($result->records[0]->devicePin)->toBe('101')
        ->and($result->records[0]->direction)->toBe(0)
        ->and($result->cursor)->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'AcsEvent')
        && str_starts_with((string) ($request->header('Authorization')[0] ?? ''), 'Digest'));
});

it('pulls Dahua access records and maps the configured direction field', function () {
    $time = now()->subMinutes(5)->format('Y-m-d H:i:s');

    Http::fake([
        '*recordFinder.cgi*' => Http::sequence()
            ->push('', 401, ['WWW-Authenticate' => 'Digest realm="Dahua", nonce="n1", qop="auth"'])
            ->push("found=1\nrecords[0].UserID=101\nrecords[0].Time={$time}\nrecords[0].Type=1\n", 200),
    ]);

    $device = vendorDevice('dahua_cgi', ['config' => ['direction_map' => ['1' => 1]]]);
    $result = (new DahuaCgiDriver)->pull($device, PullWindow::forDevice($device));

    expect($result->records)->toHaveCount(1)
        ->and($result->records[0]->devicePin)->toBe('101')
        ->and($result->records[0]->direction)->toBe(1);
});

it('pulls Suprema BioStar events with a session id', function () {
    $time = now()->subMinutes(5)->toIso8601String();

    Http::fake([
        '*/api/login' => Http::response(['Response' => []], 200, ['bs-session-id' => 'session-1']),
        '*/api/events*' => Http::response([
            'EventCollection' => [
                'rows' => [[
                    'user_id' => ['user_id' => '101'],
                    'datetime' => $time,
                    'event_type_id' => ['code' => '0x1001'],
                ]],
            ],
        ], 200),
        '*/api/logout' => Http::response('', 200),
    ]);

    $device = vendorDevice('suprema_biostar', [
        'port' => 443,
        'credentials' => ['login_id' => 'admin', 'password' => 'secret'],
        'config' => ['direction_map' => ['0x1001' => 0]],
    ]);

    $result = (new SupremaBiostarDriver)->pull($device, PullWindow::forDevice($device));

    expect($result->records)->toHaveCount(1)
        ->and($result->records[0]->devicePin)->toBe('101')
        ->and($result->records[0]->direction)->toBe(0);
});

it('reports a clear failure when vendor credentials are missing', function () {
    $device = new DeviceDescriptor(
        id: 1,
        tenantId: 1,
        name: 'Terminal',
        protocol: 'hikvision_isapi',
        host: '192.168.1.50',
        port: 80,
    );

    (new HikvisionIsapiDriver)->pull($device, PullWindow::forDevice($device));
})->throws(RuntimeException::class, 'Set the Hikvision username and password');
