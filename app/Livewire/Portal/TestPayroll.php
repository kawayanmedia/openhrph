<?php

namespace App\Livewire\Portal;

use Livewire\Component;

class TestPayroll extends Component
{
    public $title = 'Dashboard';

    public function render()
    {
        return view('livewire.portal.test')
            ->title($this->title)->layout('layouts.portal');
    }
}
