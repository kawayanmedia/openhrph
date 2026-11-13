<?php

namespace App\Livewire\Portal\Schedules;

use Livewire\Component;

class ScheduleCreate extends Component
{
    public $title = 'Schedule Adjustment';

    public $from;

    public $to;

    public $shiftStart;

    public $breakStart;

    public $breakEnd;

    public $shiftEnd;

    public function store()
    {
    }

    public function render()
    {
        return view('livewire.portal.schedules.create')
            ->title($this->title)->layout('layouts.portal');
    }
}
