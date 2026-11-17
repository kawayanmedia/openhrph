<?php

namespace App\Livewire\Portal\Attendance;

use App\Models\Timesheet;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class AttendanceReport extends Component
{
    public $title = 'My Attendance Report';

    public $employee;

    public $dateFrom;

    public $dateTo;

    protected $queryString = ['status'];

    public $rows = [];

    public function mount()
    {
        $this->query();

        $this->employee = Auth::user()->employee;
    }

    public function query($status = null)
    {
        $this->rows = Timesheet::query()
            ->where('employee_id', request()->user()?->employee?->id)
            // ->where('employee_id', 2)
            ->byDates($this->dateFrom, $this->dateTo)
            ->orderBy('log_date')
            ->limit(31)
            ->get();
    }

    public function changeDate()
    {
        $this->query();
    }

    public function render()
    {
        return view('livewire.portal.attendance.report')
            ->title($this->title)
            ->title($this->title)->layout('layouts.portal');
    }
}
