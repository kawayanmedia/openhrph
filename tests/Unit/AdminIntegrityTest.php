<?php

use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Support\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;

it('defines a valid model and uri key for every admin resource', function () {
    foreach (ResourceRegistry::available() as $class) {
        $model = $class::$model;

        expect(class_exists($model))->toBeTrue("{$class} references a missing model {$model}");
        expect(is_subclass_of($model, Model::class))->toBeTrue("{$class} model {$model} is not an Eloquent model");
        expect($class::uriKey())->not->toBeEmpty();
    }
});

it('provides a blade view for every form-capable field', function () {
    $path = app_path('Livewire/Admin/Fields');

    foreach ((new Finder)->files()->in($path)->name('*.php') as $file) {
        $class = 'App\\Livewire\\Admin\\Fields\\'.$file->getBasename('.php');

        if (! class_exists($class) || ! is_subclass_of($class, Field::class)) {
            continue;
        }

        $field = new $class;

        if ($field->showOnCreating || $field->showOnUpdating) {
            expect(view()->exists('livewire.admin.fields.'.$field->component))
                ->toBeTrue("Missing view 'livewire.admin.fields.{$field->component}' for {$class}");
        }
    }
});
