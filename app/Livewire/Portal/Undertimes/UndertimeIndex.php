<?php

namespace App\Livewire\Portal\Undertimes;

use App\Livewire\Traits\SelectableTab;
use App\Models\OvertimeRequest;
use Livewire\Component;

class UndertimeIndex extends Component
{
    public $title = 'Undertimes';

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
            ->where('type', OvertimeRequest::TYPE_UNDERTIME)
            ->byStatus($status)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.undertimes.index')
            ->title($this->title)->layout('layouts.portal');
    }
}
