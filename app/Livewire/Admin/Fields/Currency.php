<?php

namespace App\Livewire\Admin\Fields;

class Currency extends Number
{
    public string $component = 'currency';

    public string $currency = 'PHP';

    public function currency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }
}
