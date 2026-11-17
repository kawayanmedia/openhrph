<?php

namespace App\Livewire\Admin\Fields;

class Line extends Field
{
    public bool $asHeading = false;

    public bool $asSubTitle = false;

    public bool $asSmall = false;

    public function asHeading(): static
    {
        $this->asHeading = true;

        return $this;
    }

    public function asSubTitle(): static
    {
        $this->asSubTitle = true;

        return $this;
    }

    public function asSmall(): static
    {
        $this->asSmall = true;

        return $this;
    }
}
