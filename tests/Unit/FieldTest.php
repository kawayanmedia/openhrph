<?php

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;

it('builds a text field with fluent modifiers', function () {
    $field = Text::make('Name')->sortable()->rules('required');

    expect($field->name)->toBe('Name')
        ->and($field->attribute)->toBe('name')
        ->and($field->sortable)->toBeTrue()
        ->and($field->rules)->toContain('required');
});

it('derives the attribute from the field name', function () {
    expect(Text::make('Employee Code')->attribute)->toBe('employee_code');
});

it('defaults the id field to the id attribute', function () {
    $field = ID::make();

    expect($field->name)->toBe('ID')
        ->and($field->attribute)->toBe('id')
        ->and($field->component)->toBe('id');
});

it('marks badge fields as display only', function () {
    $field = new Badge;

    expect($field->showOnCreating)->toBeFalse()
        ->and($field->showOnUpdating)->toBeFalse();
});

it('keeps select options', function () {
    $field = Select::make('Status')->options(['active' => 'Active']);

    expect($field->options)->toBe(['active' => 'Active']);
});

it('computes the field type from the class name', function () {
    expect((new Text)->type())->toBe('text')
        ->and((new Badge)->type())->toBe('badge')
        ->and((new Select)->type())->toBe('select');
});
