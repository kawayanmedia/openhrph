<?php

namespace App\Livewire\Admin\Fields;

class Stack extends Field
{
    public string $component = 'stack';

    public array $lines = [];

    public function __construct()
    {
        $this->showOnDetail = false;
        $this->showOnCreating = false;
        $this->showOnUpdating = false;
    }

    public function lines(array $lines): static
    {
        $this->lines = $lines;

        return $this;
    }

    public static function make($name = null, $attribute = null): static
    {
        $field = new static;
        $field->name = $name ?? 'Stack';
        $field->attribute = $attribute;

        if (is_array($attribute)) {
            $field->lines = $attribute;
            $field->attribute = null;
        }

        return $field;
    }
}
