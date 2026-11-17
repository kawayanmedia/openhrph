<?php

namespace App\Livewire\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class BooleanGroup extends Field
{
    public string $component = 'boolean-group';

    public array $options = [];

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function resolveForForm(Model $model): mixed
    {
        $value = $model->getAttribute($this->attribute);

        return is_array($value) ? $value : [];
    }

    public function resolveForDisplay(Model $model, ?string $attribute = null): mixed
    {
        $value = parent::resolveForDisplay($model, $attribute);

        if (! is_array($value)) {
            return [];
        }

        return array_keys(array_filter($value));
    }

    public function fill(Model $model, mixed $requestValue, string $requestAttribute): void
    {
        if ($this->computed) {
            return;
        }

        $selected = is_array($requestValue) ? $requestValue : [];

        $result = [];

        foreach (array_keys($this->options) as $key) {
            if (! empty($selected[$key])) {
                $result[$key] = true;
            }
        }

        // Preserve permissions that are no longer listed in the options map.
        foreach ($selected as $key => $value) {
            if (! array_key_exists($key, $this->options) && $value) {
                $result[$key] = true;
            }
        }

        $model->setAttribute($this->attribute, $result);
    }
}
