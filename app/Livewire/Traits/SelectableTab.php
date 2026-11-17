<?php

namespace App\Livewire\Traits;

trait SelectableTab
{
    public $status = 'pending';

    public $selectedTab = 'pending';

    public $selectedTabDropdown = 'pending';

    public $options = [];

    public function selectTab($type)
    {
        $this->selectedTab = $type;

        $this->status = $type;

        $this->query($this->status);
    }

    public function updatedSelectedTabDropdown($type)
    {
        $this->status = $type;

        $this->query($this->status);
    }
}
