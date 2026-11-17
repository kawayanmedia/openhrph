<?php

namespace App\Livewire\Portal\Schedules;

use App\Livewire\Traits\SelectableTab;
use App\Models\ScheduleAdjustmentRequest;
use Livewire\Component;

class ScheduleAdjustmentIndex extends Component
{
    public $title = 'My Schedules';

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
        $this->rows = ScheduleAdjustmentRequest::query()
            ->where('employee_id', request()->user()?->employee?->id)
            ->byStatus($status)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.scheduleadjustments.index')
            ->title($this->title)->layout('layouts.portal');
    }
}
