<?php

namespace App\Livewire\Admin\Fields;

class ID extends Text
{
    public string $component = 'id';

    public function __construct()
    {
        // IDs are never editable; they only appear on index/detail.
        $this->showOnCreating = false;
        $this->showOnUpdating = false;
    }

    public static function make($name = 'ID', $attribute = 'id'): static
    {
        return parent::make($name, $attribute);
    }
}
