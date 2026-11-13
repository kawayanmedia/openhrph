<?php

namespace App\Livewire\Admin\Fields;

class Boolean extends Field
{
    public string $component = 'boolean';

    protected function castValueForStorage(mixed $value): mixed
    {
        return (bool) $value;
    }
}
