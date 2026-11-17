<?php

namespace App\Livewire\Admin\Fields;

class Heading extends Field
{
    public string $component = 'heading';

    public function __construct()
    {
        $this->showOnIndex = false;
        $this->showOnDetail = false;
    }

    public function fill(\Illuminate\Database\Eloquent\Model $model, mixed $requestValue, string $requestAttribute): void
    {
        // Headings are display-only.
    }
}
