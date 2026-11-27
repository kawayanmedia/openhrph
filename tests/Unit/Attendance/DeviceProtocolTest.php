<?php

use App\Enums\DeviceProtocol;
use KawayanMedia\Attendance\Devices\DriverManager;

it('registers a driver for every device protocol', function () {
    $drivers = app(DriverManager::class);

    foreach (DeviceProtocol::cases() as $protocol) {
        expect($drivers->has($protocol->value))->toBeTrue("No driver is registered for [{$protocol->value}].");
    }
});

it('knows which protocols can be pulled', function () {
    expect(DeviceProtocol::ZktecoUdp->isPullable())->toBeTrue()
        ->and(DeviceProtocol::HikvisionIsapi->isPullable())->toBeTrue()
        ->and(DeviceProtocol::DahuaCgi->isPullable())->toBeTrue()
        ->and(DeviceProtocol::SupremaBiostar->isPullable())->toBeTrue()
        ->and(DeviceProtocol::ZktecoAdms->isPullable())->toBeFalse()
        ->and(DeviceProtocol::GenericJson->isPush())->toBeTrue()
        ->and(DeviceProtocol::CsvExport->isPush())->toBeFalse();
});

it('normalises protocol values from imported configuration', function () {
    expect(DeviceProtocol::tryFromValue('ZKTeco-UDP'))->toBe(DeviceProtocol::ZktecoUdp)
        ->and(DeviceProtocol::tryFromValue('hikvision isapi'))->toBe(DeviceProtocol::HikvisionIsapi)
        ->and(DeviceProtocol::tryFromValue(''))->toBeNull()
        ->and(DeviceProtocol::tryFromValue('telepathy'))->toBeNull();
});
