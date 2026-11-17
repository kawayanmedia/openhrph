<?php

namespace App\Livewire\Portal\Schedules;

use Livewire\Component;

class ScheduleIndex extends Component
{
    public $title = 'My Schedules';

    public $status = 'pending';

    protected $queryString = ['status'];

    public $selectedTab = 'pending';

    public $selectedTabDropdown = 'pending';

    public $options = [];

    public function mount()
    {
        $this->options = [
            'pending' => 'Pending/Resubmitted for Editing',
            'approved' => 'Approved',
            'cancelled' => 'Rejected/ Cancelled',
        ];
    }

    public function selectTab($type)
    {
        $this->selectedTab = $type;

        $this->status = $type;

        // TODO call query to update the data
    }

    public function updatedSelectedTabDropdown()
    {

    }

    public function tabUpdated()
    {
        // Call when the
    }

    public function render()
    {
        return view('livewire.portal.schedules.index')
            ->title($this->title)->layout('layouts.portal');
    }
}
