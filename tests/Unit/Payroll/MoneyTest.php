<?php

use App\Support\Money;

$captureNextWarning = function (Closure $callback): ?array {
    $captured = null;

    set_error_handler(function (int $level, string $message) use (&$captured): bool {
        $captured = [$level, $message];

        return true;
    });

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $captured;
};

it('warns when a money result is discarded', function () use ($captureNextWarning) {
    $warning = $captureNextWarning(function (): void {
        Money::add(100, 200);
    });

    expect($warning)->not->toBeNull()
        ->and($warning[0])->toBe(E_USER_WARNING)
        ->and($warning[1])->toContain('Money::add()')
        ->and($warning[1])->toContain('should either be used');
});

it('stays silent when the result is used or explicitly discarded', function () use ($captureNextWarning) {
    $total = null;

    $used = $captureNextWarning(function () use (&$total): void {
        $total = Money::add(100, 200);
    });

    $discarded = $captureNextWarning(function (): void {
        (void) Money::sub(200, 100);
    });

    expect($used)->toBeNull()
        ->and($discarded)->toBeNull()
        ->and($total)->toBe(300);
});
