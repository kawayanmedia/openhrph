<?php

namespace App\Livewire\Admin\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Date extends Field
{
    public string $component = 'date';

    public ?string $displayFormat = 'Y-m-d';

    public function displayFormat(string $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    public function resolveForDisplay(Model $model, ?string $attribute = null): mixed
    {
        $value = parent::resolveForDisplay($model, $attribute);

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format($this->displayFormat ?? 'Y-m-d');
    }

    public function resolveForForm(Model $model): mixed
    {
        $value = parent::resolveForForm($model);

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    protected function castValueForStorage(mixed $value): mixed
    {
        return $value === null || $value === '' ? null : $value;
    }
}
