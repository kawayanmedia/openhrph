<?php

namespace App\Livewire\Portal\Attendance;

use App\Livewire\Traits\SelectableTab;
use App\Models\TimesheetRequest;
use Livewire\Component;

class AttendanceIndex extends Component
{
    public $title = 'My Certificates of Attendance';

    use SelectableTab;

    protected $queryString = ['status'];

    public $rows = [];

    public function mount()
    {
        $this->options = [
            'pending' => 'Pending/Resubmitted for Editing',
            'approved' => 'Approved',
            'rejected' => 'Rejected/ Cancelled',
        ];

        $this->query($this->status);
    }

    public function query($status = null)
    {
        $this->rows = TimesheetRequest::query()
            ->where('employee_id', request()->user()?->employee?->id)
            ->byStatus($status)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.attendance.index')
            ->title($this->title)->layout('layouts.portal');
    }
}
