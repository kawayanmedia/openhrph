<?php

use App\Support\Tenancy\TenantContext;

it('sets and forgets the active tenant', function () {
    $context = app(TenantContext::class);

    expect($context->has())->toBeFalse()
        ->and($context->id())->toBeNull();

    $context->set(7);

    expect($context->has())->toBeTrue()
        ->and($context->id())->toBe(7);

    $context->forget();

    expect($context->has())->toBeFalse()
        ->and($context->id())->toBeNull();
});

it('restores the previous tenant after running a callback', function () {
    $context = app(TenantContext::class);
    $context->set(1);

    $result = $context->run(2, fn () => $context->id());

    expect($result)->toBe(2)
        ->and($context->id())->toBe(1);
});

it('supports nested tenant runs', function () {
    $context = app(TenantContext::class);

    $context->run(1, function () use ($context) {
        expect($context->id())->toBe(1);

        $context->run(2, function () use ($context) {
            expect($context->id())->toBe(2);
        });

        expect($context->id())->toBe(1);
    });

    expect($context->has())->toBeFalse();
});
