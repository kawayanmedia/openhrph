<?php

namespace App\Livewire\Portal\Leaves;

use App\Livewire\Traits\SelectableTab;
use App\Models\LeaveRequest;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class LeaveIndex extends Component
{
    public $title = 'My Leaves';

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

        $this->employee = Auth::user()->employee;
    }

    public function query($status = null)
    {
        $this->rows = LeaveRequest::query()
            ->where('employee_id', request()->user()?->employee?->id)
            ->byStatus($status)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.leaves.index')
            ->title($this->title)
            ->title($this->title)->layout('layouts.portal');
    }
}
