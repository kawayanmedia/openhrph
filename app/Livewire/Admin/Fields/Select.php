<?php

namespace App\Livewire\Admin\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Select extends Field
{
    public string $component = 'select';

    public array $options = [];

    public bool $displayUsingLabels = false;

    public function options(array $options): static
    {
        $this->options = array_is_list($options) && $options !== []
            ? array_combine($options, $options)
            : $options;

        return $this;
    }

    public function displayUsingLabels(): static
    {
        $this->displayUsingLabels = true;

        return $this;
    }

    public function resolveForDisplay(Model $model, ?string $attribute = null): mixed
    {
        $value = parent::resolveForDisplay($model, $attribute);

        if ($this->displayUsingLabels && $value !== null) {
            return $this->options[$value] ?? $value;
        }

        return $value;
    }

    public function validationRules(bool $creating, mixed $resourceId = null): array
    {
        $rules = parent::validationRules($creating, $resourceId);

        if (! in_array('required', $rules, true) && ! in_array('nullable', $rules, true)) {
            $rules[] = 'nullable';
        }

        if (! empty($this->options)) {
            $rules[] = Rule::in(array_keys($this->options));
        }

        return $rules;
    }
}
