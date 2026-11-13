<?php

namespace App\Livewire\Portal\Leaves;

use App\Livewire\Traits\SelectableTab;
use App\Models\JobVacancy;
use App\Models\LeaveRequest;
use Livewire\Component;

class JobSuccess extends Component
{
    public $title = '';

    public $employee;

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
        $this->rows = JobVacancy::query()
            // ->where('employee_id', request()->user()?->employee?->id)
            // ->byStatus($status)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.applicants.success')
            ->title($this->title)
            ->title($this->title)->layout('layouts.job');
    }
}
