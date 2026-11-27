<?php

use Carbon\CarbonImmutable;
use KawayanMedia\Attendance\Adms\AdmsProtocol;

it('answers the handshake with the serial and the server offset', function () {
    $response = AdmsProtocol::handshake('ADMS-0001', 'Asia/Manila', ['delay' => 15, 'trans_times' => '00:00;12:00']);

    expect($response)
        ->toContain("GET OPTION FROM: ADMS-0001\r\n")
        ->toContain('Stamp=9999')
        ->toContain('Delay=15')
        ->toContain('TransTimes=00:00;12:00')
        ->toContain('ServerTZ=8')
        ->toContain('TimeZone=8');
});

it('handles fractional timezone offsets', function () {
    expect(AdmsProtocol::offsetHours('Asia/Kolkata'))->toBe('5.5');
});

it('maps the attendance status column to a punch direction', function () {
    expect(AdmsProtocol::directionFor(0))->toBe(0)
        ->and(AdmsProtocol::directionFor(1))->toBe(1)
        ->and(AdmsProtocol::directionFor(2))->toBe(1)
        ->and(AdmsProtocol::directionFor(3))->toBe(0)
        ->and(AdmsProtocol::directionFor(4))->toBe(0)
        ->and(AdmsProtocol::directionFor(5))->toBe(1)
        ->and(AdmsProtocol::directionFor(9))->toBeNull()
        ->and(AdmsProtocol::directionFor(null))->toBeNull();
});

it('parses tab separated attendance lines', function () {
    $body = "1001\t2026-11-12 08:01:15\t0\t1\t0\r\n"
        ."1001\t2026-11-12 17:02:45\t1\t1\t0\r\n"
        ."\r\n";

    $records = AdmsProtocol::parseAttendance($body, 'Asia/Manila');

    expect($records)->toHaveCount(2)
        ->and($records[0]->devicePin)->toBe('1001')
        ->and($records[0]->direction)->toBe(0)
        ->and($records[0]->punchedAt->format('Y-m-d H:i:s'))->toBe('2026-11-12 08:01:15')
        ->and($records[1]->direction)->toBe(1);
});

it('keeps alphanumeric pins and skips malformed lines', function () {
    $body = "not-a-punch\n"
        ."EMP-A1\t2026-11-12 08:00:00\t0\n"
        ."1001\tnot-a-date\t0\n";

    $records = AdmsProtocol::parseAttendance($body);

    expect($records)->toHaveCount(1)
        ->and($records[0]->devicePin)->toBe('EMP-A1')
        ->and($records[0]->punchedAt)->toBeInstanceOf(CarbonImmutable::class);
});

it('counts non-empty lines for the device acknowledgement', function () {
    expect(AdmsProtocol::lineCount("a\r\nb\r\n\r\n"))->toBe(2)
        ->and(AdmsProtocol::accepted(3))->toBe('OK: 3');
});
