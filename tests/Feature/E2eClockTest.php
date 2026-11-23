<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('e2e-clock-probe', fn (): string => now()->format('Y-m-d H:i:s'));

    Carbon::setTestNow();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shifts the application clock when the e2e header is present', function () {
    $this->withHeader('X-E2E-Now', '2026-11-04T08:20:00+08:00')
        ->get('e2e-clock-probe')
        ->assertOk()
        ->assertSee('2026-11-04 08:20:00');
});

it('leaves the real clock alone without the header', function () {
    Carbon::setTestNow('2026-11-12 09:00:00');

    $this->get('e2e-clock-probe')
        ->assertOk()
        ->assertSee('2026-11-12 09:00:00');
});

it('restores the real clock after the request', function () {
    $this->withHeader('X-E2E-Now', '2026-11-04T08:20:00+08:00')
        ->get('e2e-clock-probe')
        ->assertOk()
        ->assertSee('2026-11-04 08:20:00');

    expect(abs(now()->getTimestamp() - time()))->toBeLessThan(5);
});

it('rejects the header outside local environments', function () {
    app()['env'] = 'production';

    $this->withHeader('X-E2E-Now', '2026-11-04T08:20:00+08:00')
        ->get('e2e-clock-probe')
        ->assertForbidden();
});
