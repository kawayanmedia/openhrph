<?php

namespace App\Livewire\Admin\Fields;

class HasMany extends Field
{
    public string $component = 'has-many';

    public ?string $resource = null;

    public function __construct()
    {
        // HasMany fields are rendered separately by the detail page's relationFields()
        // list, so they must not be resolved as scalar values by field-display.
        $this->showOnIndex = false;
        $this->showOnDetail = false;
        $this->showOnCreating = false;
        $this->showOnUpdating = false;
        $this->readonly = true;
    }

    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }
}
