<?php

namespace App\Livewire\Admin\Fields;

use App\Livewire\Admin\Support\FormData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class Field
{
    /**
     * The displayable name of the field.
     */
    public string $name;

    /**
     * The underlying model attribute (column or relationship) name.
     */
    public ?string $attribute;

    /**
     * The Blade view used to render the field on forms.
     */
    public string $component = 'text';

    public bool $sortable = false;

    public bool $nullable = false;

    public bool $readonly = false;

    public bool $searchable = false;

    public bool $filterable = false;

    public bool $showOnIndex = true;

    public bool $showOnDetail = true;

    public bool $showOnCreating = true;

    public bool $showOnUpdating = true;

    public mixed $default = null;

    public ?string $help = null;

    public ?string $placeholder = null;

    public array $meta = [];

    public array $rules = [];

    public array $creationRules = [];

    public array $updateRules = [];

    /** @var callable|null */
    public $displayUsing = null;

    /** @var callable|null */
    public $resolveUsing = null;

    /**
     * Whether the field is a computed field (a closure was passed as the attribute).
     */
    public bool $computed = false;

    /**
     * Whether the resolved value should be rendered as raw HTML.
     */
    public bool $asHtml = false;

    /** @var callable|null */
    public $fillUsing = null;

    /** @var callable|null */
    public $indexCallback = null;

    /**
     * Attributes this field depends on for conditional behaviour.
     *
     * @var array<int, string>
     */
    public array $dependsOnFields = [];

    /** @var callable|null */
    public $dependsOnCallback = null;

    /**
     * Whether the input should update the component on every change.
     */
    public bool $live = false;

    /**
     * Create a new field.
     */
    public static function make($name = null, $attribute = null): static
    {
        $field = new static;
        $field->name = $name ?? Str::title(Str::snake(class_basename(static::class), ' '));

        if ($attribute instanceof \Closure) {
            $field->attribute = null;
            $field->computed = true;
            $field->displayUsing = $attribute;
            $field->showOnCreating = false;
            $field->showOnUpdating = false;
        } else {
            $field->attribute = $attribute ?? Str::snake($field->name);
        }

        return $field;
    }

    public function component(string $component): static
    {
        $this->component = $component;

        return $this;
    }

    public function sortable(): static
    {
        $this->sortable = true;

        return $this;
    }

    public function nullable(): static
    {
        $this->nullable = true;

        return $this;
    }

    /**
     * Register a conditional callback evaluated against the current form values.
     *
     * @param  array<int, string>  $fields
     */
    public function dependsOn(array $fields, callable $callback): static
    {
        $this->dependsOnFields = $fields;
        $this->dependsOnCallback = $callback;

        return $this;
    }

    /**
     * Apply the conditional callback, if one exists.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyDependencies(array|FormData $data, ?Request $request = null): void
    {
        if ($this->dependsOnCallback === null) {
            return;
        }

        call_user_func(
            $this->dependsOnCallback,
            $this,
            $request ?? request(),
            $data instanceof FormData
                ? $data
                : new FormData($data)
        );
    }

    /**
     * Whether the field targets a relation path (e.g. `employee.employee_number`).
     *
     * Such attributes are display-only columns and are never writable form inputs.
     */
    public function hasRelationAttribute(): bool
    {
        return $this->attribute !== null && str_contains($this->attribute, '.');
    }

    /**
     * Whether the field is editable through a form input.
     */
    public function isFormInput(): bool
    {
        return ! $this->computed
            && $this->attribute !== null
            && ! $this->hasRelationAttribute();
    }

    /**
     * Whether the field value should be copied into the model on save.
     */
    public function canFillFromForm(): bool
    {
        return ! $this->computed
            && $this->attribute !== null
            && $this->component !== 'heading'
            && ! $this->hasRelationAttribute();
    }

    public function readonly(): static
    {
        $this->readonly = true;

        return $this;
    }

    public function searchable(): static
    {
        $this->searchable = true;

        return $this;
    }

    public function filterable(): static
    {
        $this->filterable = true;

        return $this;
    }

    public function showOnIndex(): static
    {
        $this->showOnIndex = true;

        return $this;
    }

    public function hideFromIndex(): static
    {
        $this->showOnIndex = false;

        return $this;
    }

    public function showOnDetail(): static
    {
        $this->showOnDetail = true;

        return $this;
    }

    public function hideFromDetail(): static
    {
        $this->showOnDetail = false;

        return $this;
    }

    public function hideWhenCreating(): static
    {
        $this->showOnCreating = false;

        return $this;
    }

    public function hideWhenUpdating(): static
    {
        $this->showOnUpdating = false;

        return $this;
    }

    public function show(): static
    {
        $this->showOnCreating = true;
        $this->showOnUpdating = true;

        return $this;
    }

    public function hide(): static
    {
        $this->showOnCreating = false;
        $this->showOnUpdating = false;

        return $this;
    }

    public function onlyOnForms(): static
    {
        return $this->hideFromIndex()->hideFromDetail();
    }

    public function onlyOnDetail(): static
    {
        $this->showOnIndex = false;
        $this->showOnCreating = false;
        $this->showOnUpdating = false;

        return $this;
    }

    public function onlyOnIndex(): static
    {
        $this->showOnDetail = false;
        $this->showOnCreating = false;
        $this->showOnUpdating = false;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function help(?string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function rules(...$rules): static
    {
        $this->rules = $this->normalizeRules($rules);

        return $this;
    }

    public function creationRules(...$rules): static
    {
        $this->creationRules = $this->normalizeRules($rules);

        return $this;
    }

    public function updateRules(...$rules): static
    {
        $this->updateRules = $this->normalizeRules($rules);

        return $this;
    }

    public function displayUsing(callable $callback): static
    {
        $this->displayUsing = $callback;

        return $this;
    }

    public function resolveUsing(callable $callback): static
    {
        $this->resolveUsing = $callback;

        return $this;
    }

    public function fillUsing(callable $callback): static
    {
        $this->fillUsing = $callback;

        return $this;
    }

    public function indexUsing(callable $callback): static
    {
        $this->indexCallback = $callback;

        return $this;
    }

    public function asHtml(): static
    {
        $this->asHtml = true;

        return $this;
    }

    /**
     * Resolve the value for display (index/detail).
     */
    public function resolveForDisplay(Model $model, ?string $attribute = null): mixed
    {
        $attribute ??= $this->attribute;

        if ($this->computed && $this->displayUsing instanceof \Closure) {
            return \Closure::bind($this->displayUsing, $model)();
        }

        $value = $this->resolveAttributeValue($model, $attribute);

        if ($this->displayUsing) {
            return call_user_func($this->displayUsing, $value, $model);
        }

        if ($this->resolveUsing) {
            return call_user_func($this->resolveUsing, $value, $model);
        }

        return $value;
    }

    /**
     * Resolve the value for a form field.
     */
    public function resolveForForm(Model $model): mixed
    {
        $value = $this->resolveAttributeValue($model, $this->attribute);

        if ($this->resolveUsing) {
            return call_user_func($this->resolveUsing, $value, $model);
        }

        return $value;
    }

    /**
     * Resolve an attribute value, following `relation.column` paths when present.
     */
    protected function resolveAttributeValue(Model $model, ?string $attribute): mixed
    {
        if ($attribute === null) {
            return null;
        }

        if (str_contains($attribute, '.')) {
            return data_get($model, $attribute);
        }

        return $model->getAttribute($attribute);
    }

    /**
     * Fill the model with the incoming form value.
     */
    public function fill(Model $model, mixed $requestValue, string $requestAttribute): void
    {
        if ($this->computed) {
            return;
        }

        if ($this->fillUsing) {
            call_user_func($this->fillUsing, $model, $this->attribute, $requestValue, $requestAttribute);

            return;
        }

        $model->setAttribute($this->attribute, $this->castValueForStorage($requestValue));
    }

    protected function castValueForStorage(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Get the validation rules for this field.
     */
    public function validationRules(bool $creating, mixed $resourceId = null): array
    {
        $rules = array_merge($this->rules, $creating ? $this->creationRules : $this->updateRules);

        if ($this->nullable && ! in_array('required', $rules, true)) {
            $rules[] = 'nullable';
        }

        return $this->resolveResourceIdInRules($rules, $resourceId);
    }

    /**
     * Whether the field is required for a create form.
     */
    public function isRequired(): bool
    {
        foreach ($this->validationRules(true) as $rule) {
            if (is_string($rule) && in_array('required', preg_split('/\s*\|\s*/', $rule) ?: [], true)) {
                return true;
            }
        }

        return false;
    }

    public function shouldValidate(bool $creating, bool $updating): bool
    {
        if ($creating) {
            return $this->showOnCreating;
        }

        return $updating && $this->showOnUpdating;
    }

    protected function resolveResourceIdInRules(array $rules, mixed $resourceId): array
    {
        return array_map(function ($rule) use ($resourceId) {
            if (is_string($rule) && str_contains($rule, '{{resourceId}}')) {
                return str_replace('{{resourceId}}', (string) $resourceId, $rule);
            }

            return $rule;
        }, $rules);
    }

    public function type(): string
    {
        return strtolower(class_basename(static::class));
    }

    protected function normalizeRules(array $rules): array
    {
        return array_values(array_filter($rules, fn ($rule) => $rule !== null));
    }
}
