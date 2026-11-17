<?php

namespace App\Livewire\Portal\OfficialBusiness;

use App\Livewire\Traits\SelectableTab;
use App\Models\OfficialBusinessRequest;
use Livewire\Component;

class OfficialBusinessIndex extends Component
{
    public $title = 'My Official Business';

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
        $this->rows = OfficialBusinessRequest::query()
            ->where('employee_id', request()->user()?->employee?->id)
            ->byStatus($status)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.officialbusiness.index')
            ->title($this->title)->layout('layouts.portal');
    }
}
