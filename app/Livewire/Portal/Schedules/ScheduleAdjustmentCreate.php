<?php

namespace App\Livewire\Portal\Schedules;

use App\Models\Schedule;
use App\Models\ScheduleAdjustmentRequest;
use App\Support\Tenancy\TenantCache;
use Illuminate\Http\Request;
use Livewire\Component;

class ScheduleAdjustmentCreate extends Component
{
    public $title = 'Schedule Adjustment';

    public $dateFrom;

    public $dateTo;

    public $shiftStart;

    public $breakStart;

    public $breakEnd;

    public $shiftEnd;

    public $schedulesOptions = [];

    public $scheduleId;

    public function mount(Request $request)
    {
        $this->schedulesOptions = TenantCache::rememberForever('schedule_types', function () {
            return Schedule::pluck('name', 'id')->toArray();
        });

        $this->schedulesOptions['rd'] = 'Rest Day';
    }

    public function store()
    {
        $this->validate([
            'dateFrom' => 'required',
            'dateTo' => 'required',
            // 'shiftStart' => 'required',
            // 'breakStart' => 'required',
            // 'breakEnd' => 'required',
            // 'shiftEnd' => 'required',
        ]);

        $schedule = new ScheduleAdjustmentRequest;
        $schedule->employee_id = request()->user()?->employee?->id;
        $schedule->date_filed = now()->format('Y-m-d');
        $schedule->date_from = $this->dateFrom;
        $schedule->date_to = $this->dateTo;
        $schedule->time_from = $this->shiftStart;
        $schedule->break_time_start = $this->breakStart;
        $schedule->break_time_end = $this->breakEnd;
        $schedule->time_to = $this->shiftEnd;

        if ($this->scheduleId == 'rd') {
            $schedule->is_rd = true;
        } else {
            $schedule->schedule_id = $this->scheduleId;
        }
        $schedule->save();

        return redirect()->route('portal.schedulesadjustment');
    }

    public function render()
    {
        return view('livewire.portal.scheduleadjustments.create')
            ->title($this->title)->layout('layouts.portal');
    }
}
