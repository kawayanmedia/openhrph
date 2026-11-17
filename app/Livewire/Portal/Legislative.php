<?php

namespace App\Livewire\Portal;

use App\Services\Payroll\ProcessPayrollEngine;
use Livewire\Component;

class Legislative extends Component
{
    public $title = 'Dashboard';

    public function mount()
    {
        $engine = new ProcessPayrollEngine;
        // $engine->handlePayroll();
    }

    public function render()
    {
        return view('livewire.portal.legislative')
            ->title($this->title)->layout('layouts.legislative');
    }
}
