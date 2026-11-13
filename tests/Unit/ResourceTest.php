<?php

use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Resources\Office;
use App\Models\Office as OfficeModel;

it('resolves the uri key from the class name', function () {
    expect(Office::uriKey())->toBe('office');
});

it('returns a new model instance for a resource', function () {
    expect(Office::newModel())->toBeInstanceOf(OfficeModel::class);
});

it('builds fields for a resource', function () {
    $fields = (new Office)->fields(request());

    expect($fields)->toBeArray()->not->toBeEmpty()
        ->and($fields[0])->toBeInstanceOf(Field::class);
});

it('filters index fields to those visible on the index', function () {
    $indexFields = (new Office)->indexFields(request());

    expect($indexFields)->not->toBeEmpty();

    foreach ($indexFields as $field) {
        expect($field->showOnIndex)->toBeTrue();
    }
});

it('excludes hidden fields from the index listing', function () {
    $indexFields = (new Office)->indexFields(request());

    $components = array_map(fn (Field $field) => $field->component, $indexFields);

    expect($components)->not->toContain('has-many');
});
