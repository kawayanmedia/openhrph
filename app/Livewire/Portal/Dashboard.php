<?php

namespace App\Livewire\Portal;

use App\Models\Announcement;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public $title = 'Dashboard';

    public $announcements = [];

    public $employee;

    public $showViewAll = true;

    public function mount()
    {
        $this->announcements = Announcement::latest()->limit(3)->get();

        $this->employee = Auth::user()->employee;

    }

    public function viewAll()
    {
        $this->announcements = Announcement::latest()->get();

        $this->showViewAll = false;
    }

    public function render()
    {
        return view('livewire.portal.dashboard')
            ->title($this->title)->layout('layouts.portal');
    }
}
