<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Support\FormData;
use App\Livewire\Admin\Support\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;

class AdminForm extends Component
{
    use WithFileUploads;

    public string $resourceKey;

    /** @var resource */
    public $resource;

    public ?int $resourceId = null;

    public array $form = [];

    public bool $editing = false;

    public function mount(string $resource, ?int $resourceId = null): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $this->resourceKey = $resource;
        $this->resource = ResourceRegistry::resolve($resource);
        $this->resourceId = $resourceId;
        $this->editing = $resourceId !== null;

        $model = $this->editing
            ? $this->resource::newModel()->newQuery()->findOrFail($this->resourceId)
            : $this->resource::newModel();

        $this->resource::authorize($this->editing ? 'update' : 'create', $this->editing ? $model : $this->resource::$model);

        if ($this->editing) {
            $this->resource->resource = $model;
        }

        $this->initializeForm($model);
    }

    public function render()
    {
        $fields = $this->formFields();

        return view('livewire.admin.form', [
            'fields' => $fields,
        ])->layout('layouts.admin')->title(($this->editing ? 'Edit ' : 'Create ').$this->resource::label());
    }

    public function save(): void
    {
        $fields = $this->formFields();

        $model = $this->editing
            ? $this->resource::newModel()->newQuery()->findOrFail($this->resourceId)
            : $this->resource::newModel();

        $this->resource::authorize($this->editing ? 'update' : 'create', $this->editing ? $model : $this->resource::$model);

        $this->validateForm($fields);

        foreach ($fields as $field) {
            if (! $field->canFillFromForm()) {
                continue;
            }

            $attribute = $field->attribute;

            if (! array_key_exists($attribute, $this->form)) {
                continue;
            }

            $value = $this->form[$attribute];

            // Read-only fields are only written when a dependency callback changed them.
            if ($field->readonly && $this->editing && (string) $value === (string) $model->getAttribute($attribute)) {
                continue;
            }

            $field->fill($model, $value, $attribute);
        }

        // Make the submitted values available to afterCreate/afterUpdate hooks.
        request()->merge($this->form);

        $model->save();

        if ($this->editing) {
            $this->resource::afterUpdate(request(), $model);
        } else {
            $this->resource::afterCreate(request(), $model);
        }

        session()->flash('admin.saved', $this->editing ? 'Record updated.' : 'Record created.');

        $redirect = $this->editing
            ? $this->resource::redirectAfterUpdate(request(), $model)
            : $this->resource::redirectAfterCreate(request(), $model);

        if (is_string($redirect) && $redirect !== '') {
            $this->redirect($redirect);

            return;
        }

        $this->redirectRoute('admin.resource.detail', [
            'resource' => $this->resourceKey,
            'resourceId' => $model->getKey(),
        ]);
    }

    /**
     * @return array<int, Field>
     */
    protected function formFields(): array
    {
        $fields = $this->resource->formFields(request(), ! $this->editing);

        $this->resolveDependencies($fields);

        return $fields;
    }

    /**
     * Run dependsOn callbacks against the current form state and flag live inputs.
     *
     * @param  array<int, Field>  $fields
     * @return array<int, Field>
     */
    protected function resolveDependencies(array $fields): array
    {
        $formData = new FormData($this->form);

        foreach ($fields as $field) {
            $field->applyDependencies($formData, request());
        }

        $this->form = $formData->toArray();

        $sources = [];

        foreach ($fields as $field) {
            foreach ($field->dependsOnFields as $dependency) {
                $sources[$dependency] = true;
            }
        }

        foreach ($fields as $field) {
            $field->live = $field->attribute !== null && isset($sources[$field->attribute]);
        }

        return $fields;
    }

    protected function initializeForm(Model $model): void
    {
        foreach ($this->resource->formFields(request(), ! $this->editing) as $field) {
            $attribute = $field->attribute;

            if ($attribute === null) {
                continue;
            }

            if ($this->editing) {
                $this->form[$attribute] = $field->resolveForForm($model);
            } else {
                $default = $field->default;

                $this->form[$attribute] = $default instanceof \Closure ? $default() : $default;
            }
        }
    }

    /**
     * @param  array<int, Field>  $fields
     */
    protected function validateForm(array $fields): void
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            if (! $field->isFormInput() || ! $field->shouldValidate(! $this->editing, $this->editing)) {
                continue;
            }

            $fieldRules = $field->validationRules(! $this->editing, $this->resourceId);

            if (empty($fieldRules)) {
                continue;
            }

            $key = 'form.'.$field->attribute;
            $rules[$key] = $fieldRules;
            $attributes[$key] = $field->name ?: (string) $field->attribute;
        }

        if (empty($rules)) {
            $this->resetErrorBag();

            return;
        }

        $this->validate($rules, [], $attributes);

        // Reset stale errors for fields that no longer have rules.
        foreach ($fields as $field) {
            if ($field->attribute !== null && ! isset($rules['form.'.$field->attribute])) {
                $this->resetErrorBag('form.'.$field->attribute);
            }
        }
    }
}
