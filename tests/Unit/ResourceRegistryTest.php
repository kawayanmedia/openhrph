<?php

use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Resources\Office;
use App\Livewire\Admin\Support\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('discovers all admin resources', function () {
    $classes = ResourceRegistry::available();

    expect($classes)->not->toBeEmpty();
    expect(count($classes))->toBe(56);

    foreach ($classes as $class) {
        expect(is_subclass_of($class, Resource::class))->toBeTrue();
    }
});

it('keeps resource uri keys unique', function () {
    $keys = array_map(fn (string $class) => $class::uriKey(), ResourceRegistry::available());

    expect($keys)->toBe(array_values(array_unique($keys)));
});

it('finds a resource by its uri key', function () {
    expect(ResourceRegistry::find('office'))->toBe(Office::class);
});

it('resolves a resource instance by its uri key', function () {
    expect(ResourceRegistry::resolve('office'))->toBeInstanceOf(Office::class);
});

it('throws a 404 for unknown resources', function () {
    ResourceRegistry::resolve('does-not-exist');
})->throws(NotFoundHttpException::class);
