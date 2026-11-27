<?php

use KawayanMedia\Attendance\Import\Parsers\GenericCsvParser;
use KawayanMedia\Attendance\Import\Parsers\HikvisionCsvParser;
use KawayanMedia\Attendance\Import\Parsers\ParserResolver;
use KawayanMedia\Attendance\Import\Parsers\ZktecoDatParser;

it('parses a full ZKTeco DAT export and uses the punch type column', function () {
    $dat = "1001\t2026-11-12 08:01:15\t1\t1\t0\t0\n"
        ."1001\t2026-11-12 17:02:45\t1\t1\t0\t1\n"
        ."No\tName\tDate\tTime\n";

    $records = (new ZktecoDatParser)->parse($dat, 'Asia/Manila');

    expect($records)->toHaveCount(2)
        ->and($records[0]->devicePin)->toBe('1001')
        ->and($records[0]->direction)->toBe(0)
        ->and($records[1]->direction)->toBe(1)
        ->and($records[0]->punchedAt->format('H:i:s'))->toBe('08:01:15');
});

it('parses a short DAT export using the state column', function () {
    $records = (new ZktecoDatParser)->parse("1001\t2026-11-12 08:00:00\t0\n1001\t2026-11-12 17:00:00\t1\n");

    expect($records)->toHaveCount(2)
        ->and($records[0]->direction)->toBe(0)
        ->and($records[1]->direction)->toBe(1);
});

it('parses a CSV export with named columns', function () {
    $csv = "Employee ID,Date,Time,Type\n"
        ."1001,2026-11-12,08:01:15,IN\n"
        ."1001,2026-11-12,17:02:45,OUT\n";

    $records = (new GenericCsvParser)->parse($csv, 'Asia/Manila');

    expect($records)->toHaveCount(2)
        ->and($records[0]->direction)->toBe(0)
        ->and($records[1]->direction)->toBe(1)
        ->and($records[1]->punchedAt->format('Y-m-d H:i:s'))->toBe('2026-11-12 17:02:45');
});

it('parses a positional CSV export without a header', function () {
    $records = (new GenericCsvParser)->parse("EMP-A1,2026-11-12 08:00:00,0\nEMP-A1,2026-11-12 17:00:00,1\n");

    expect($records)->toHaveCount(2)
        ->and($records[0]->devicePin)->toBe('EMP-A1')
        ->and($records[0]->direction)->toBe(0)
        ->and($records[1]->direction)->toBe(1);
});

it('splits a date and a time column', function () {
    $csv = "User ID,Name,Date,Time,Device\n"
        ."1001,Ana Cruz,2026-11-12,08:01:15,BioStation\n";

    expect((new GenericCsvParser)->parse($csv))->toHaveCount(1)
        ->and((new GenericCsvParser)->parse($csv)[0]->punchedAt->format('H:i'))->toBe('08:01');
});

it('recognises vendor exports before falling back to the generic parser', function () {
    $resolver = new ParserResolver;

    expect($resolver->resolve('hikvision.csv', "Employee No.,Name,Card No.,Door No.,Event Time\n1001,Ana,1,1,2026-11-12 08:00:00\n"))
        ->toBeInstanceOf(HikvisionCsvParser::class);

    expect($resolver->resolve('suprema.csv', "User ID,User Name,Date,Time,Device,Event\n1001,Ana,2026-11-12,08:00:00,Station,IN\n")->name())
        ->toBe('suprema-csv');

    expect($resolver->resolve('export.csv', "Employee ID,Date,Time\n1001,2026-11-12,08:00:00\n")->name())
        ->toBe('generic-csv');
});

it('refuses an unrecognised file', function () {
    (new ParserResolver)->resolve('notes.csv', "just some prose\nwithout any columns\n");
})->throws(RuntimeException::class);
