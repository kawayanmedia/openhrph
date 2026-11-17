<?php

namespace App\Livewire\Portal\Overtimes;

use App\Livewire\Traits\SelectableTab;
use App\Models\OvertimeRequest;
use Livewire\Component;

class OvertimeIndex extends Component
{
    public $title = 'My Overtimes';

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
        $this->rows = OvertimeRequest::query()
            ->where('employee_id', request()->user()?->employee?->id)
            ->where('type', OvertimeRequest::TYPE_OVERTIME)
            ->byStatus($status)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.overtimes.index')
            ->title($this->title)->layout('layouts.portal');
    }
}
