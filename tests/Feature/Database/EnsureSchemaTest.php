<?php

use App\Support\Database\TableCharsets;
use App\Support\Database\TableEngines;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

beforeEach(function () {
    // The command tests must never touch the real schema, so both services
    // report "not supported" unless the test overrides one of them.
    $this->mock(TableEngines::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsInnoDb')->andReturnFalse();
    });

    $this->mock(TableCharsets::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsUtf8mb4')->andReturnFalse();
    });
});

it('reports legacy engines without converting them in check mode', function () {
    $this->mock(TableEngines::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsInnoDb')->andReturnTrue();
        $mock->shouldReceive('nonTransactionalTables')->andReturn(['companies' => 'MyISAM']);
        $mock->shouldNotReceive('convert');
    });

    $this->artisan('db:ensure-schema', ['--check' => true])
        ->expectsOutputToContain('companies')
        ->assertFailed();
});

it('converts every non-transactional table', function () {
    $this->mock(TableEngines::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsInnoDb')->andReturnTrue();
        $mock->shouldReceive('nonTransactionalTables')->andReturn([
            'companies' => 'MyISAM',
            'timesheets' => 'MyISAM',
        ]);
        $mock->shouldReceive('convert')->once()->with('companies');
        $mock->shouldReceive('convert')->once()->with('timesheets');
    });

    $this->artisan('db:ensure-schema')
        ->expectsOutputToContain('2 table(s) converted to InnoDB.')
        ->assertSuccessful();
});

it('reports success when every table uses InnoDB', function () {
    $this->mock(TableEngines::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsInnoDb')->andReturnTrue();
        $mock->shouldReceive('nonTransactionalTables')->andReturn([]);
        $mock->shouldNotReceive('convert');
    });

    $this->artisan('db:ensure-schema', ['--check' => true])
        ->expectsOutputToContain('Storage engines: every table uses InnoDB.')
        ->assertSuccessful();
});

it('skips connections that have no selectable storage engines', function () {
    $this->artisan('db:ensure-schema', ['--check' => true])
        ->expectsOutputToContain('Storage engines: skipped')
        ->assertSuccessful();
});

it('reports legacy collations without converting them in check mode', function () {
    $this->mock(TableCharsets::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsUtf8mb4')->andReturnTrue();
        $mock->shouldReceive('collation')->andReturn('utf8mb4_unicode_ci');
        $mock->shouldReceive('nonConformingTables')->andReturn(['companies' => 'latin1_swedish_ci']);
        $mock->shouldNotReceive('convert');
    });

    $this->artisan('db:ensure-schema', ['--check' => true])
        ->expectsOutputToContain('companies')
        ->assertFailed();
});

it('converts every table with a legacy collation', function () {
    $this->mock(TableCharsets::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsUtf8mb4')->andReturnTrue();
        $mock->shouldReceive('charset')->andReturn('utf8mb4');
        $mock->shouldReceive('nonConformingTables')->andReturn([
            'companies' => 'latin1_swedish_ci',
            'compensations' => 'utf8mb3_general_ci',
        ]);
        $mock->shouldReceive('convert')->once()->with('companies');
        $mock->shouldReceive('convert')->once()->with('compensations');
    });

    $this->artisan('db:ensure-schema')
        ->expectsOutputToContain('2 table(s) converted to utf8mb4.')
        ->assertSuccessful();
});

it('reports success when every table uses the configured collation', function () {
    $this->mock(TableCharsets::class, function (MockInterface $mock): void {
        $mock->shouldReceive('supportsUtf8mb4')->andReturnTrue();
        $mock->shouldReceive('collation')->andReturn('utf8mb4_unicode_ci');
        $mock->shouldReceive('nonConformingTables')->andReturn([]);
        $mock->shouldNotReceive('convert');
    });

    $this->artisan('db:ensure-schema', ['--check' => true])
        ->expectsOutputToContain('Character sets: every table uses utf8mb4_unicode_ci.')
        ->assertSuccessful();
});

it('skips character sets on connections without them', function () {
    $this->artisan('db:ensure-schema', ['--check' => true])
        ->expectsOutputToContain('Character sets: skipped')
        ->assertSuccessful();
});

it('converts a MyISAM table so its writes roll back', function () {
    $table = 'engine_probe_'.Str::lower(Str::random(8));

    DB::statement("create table `{$table}` (id int unsigned not null auto_increment primary key, name varchar(32) not null) engine = MyISAM");

    try {
        $engines = new TableEngines;

        expect($engines->nonTransactionalTables())->toHaveKey($table);

        $engines->convert($table);

        expect($engines->nonTransactionalTables())->not->toHaveKey($table);

        DB::beginTransaction();
        DB::table($table)->insert(['name' => 'probe']);
        DB::rollBack();

        expect(DB::table($table)->count())->toBe(0);
    } finally {
        DB::statement("drop table if exists `{$table}`");
    }
})->skip(fn (): bool => DB::connection()->getDriverName() !== 'mysql', 'Storage engines are a MySQL feature.');

it('converts a legacy table so it stores utf8mb4 text', function () {
    $table = 'charset_probe_'.Str::lower(Str::random(8));

    DB::statement("create table `{$table}` (id int unsigned not null auto_increment primary key, name varchar(64) not null) engine = InnoDB default character set = latin1 collate = latin1_swedish_ci");

    try {
        $charsets = new TableCharsets;

        expect($charsets->nonConformingTables())->toHaveKey($table);

        $charsets->convert($table);

        expect($charsets->nonConformingTables())->not->toHaveKey($table);

        DB::table($table)->insert(['name' => '😀 Ünïcode']);

        expect(DB::table($table)->value('name'))->toBe('😀 Ünïcode');
    } finally {
        DB::statement("drop table if exists `{$table}`");
    }
})->skip(fn (): bool => DB::connection()->getDriverName() !== 'mysql', 'Character sets are a MySQL feature.');
