<?php

namespace App\Livewire\Portal\Overtimes;

use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Livewire\Component;

class OvertimeCreate extends Component
{
    public $title = 'Schedule Adjustment';

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
        $overtime->type = OvertimeRequest::TYPE_OVERTIME;
        $overtime->save();

        return redirect()->route('portal.overtimes');
    }

    public function updatedTimeFrom($value)
    {
        $this->computeHours();
    }

    public function updatedTimeTo($value)
    {
        $this->computeHours();
    }

    public function computeHours()
    {
        if ($this->timeFrom != null && $this->timeTo != null) {
            $this->hours = Carbon::createFromFormat('H:i', $this->timeFrom)->diffInHours(Carbon::createFromFormat('H:i', $this->timeTo));
        }
    }

    public function render()
    {
        return view('livewire.portal.overtimes.create')
            ->title($this->title)->layout('layouts.portal');
    }
}
