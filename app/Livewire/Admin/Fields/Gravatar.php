<?php

namespace App\Livewire\Admin\Fields;

class Gravatar extends Field
{
    public string $component = 'gravatar';

    public int $maxWidth = 50;

    public function __construct()
    {
        // Gravatar is a display-only field.
        $this->showOnCreating = false;
        $this->showOnUpdating = false;
    }

    public function maxWidth(int $maxWidth): static
    {
        $this->maxWidth = $maxWidth;

        return $this;
    }
}
