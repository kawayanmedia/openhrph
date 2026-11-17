<?php

namespace App\Livewire\Admin\Fields;

class Status extends Select
{
    public string $component = 'status';

    public array $loadingWhen = [];
    public array $failedWhen = [];

    public function __construct()
    {
        // Status is a display-only field, matching Nova's Status field.
        $this->showOnCreating = false;
        $this->showOnUpdating = false;
    }

    public function loadingWhen(array $values): static
    {
        $this->loadingWhen = $values;

        return $this;
    }

    public function failedWhen(array $values): static
    {
        $this->failedWhen = $values;

        return $this;
    }
}
