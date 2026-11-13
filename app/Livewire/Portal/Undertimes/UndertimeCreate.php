<?php

namespace App\Livewire\Portal\Undertimes;

use App\Models\OvertimeRequest;
use Livewire\Component;

class UndertimeCreate extends Component
{
    public $title = 'Undertime Create';

    public $date;

    public $timeFrom;

    public $timeTo;

    public $hours;

    public $reason;

    public function store()
    {
        $this->validate([
            'date' => 'required',
            'timeFrom' => 'required',
            'timeTo' => 'required',
            'hours' => 'required',
            'reason' => 'required',
        ]);

        $overtime = new OvertimeRequest;
        $overtime->employee_id = request()->user()?->employee?->id;
        $overtime->date_filed = now()->format('Y-m-d');
        $overtime->date = $this->date;
        $overtime->time_from = $this->timeFrom;
        $overtime->time_to = $this->timeTo;
        $overtime->hours = $this->hours;
        $overtime->reason = $this->reason;
        $overtime->type = OvertimeRequest::TYPE_UNDERTIME;
        $overtime->save();

        return redirect()->route('portal.undertimes');
    }

    public function render()
    {
        return view('livewire.portal.undertimes.create')
            ->title($this->title)->layout('layouts.portal');
    }
}
