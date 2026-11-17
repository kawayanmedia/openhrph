<?php

namespace App\Livewire\Portal\Attendance;

use App\Actions\CreateAttendanceAction;
use Illuminate\Support\Facades\Request;
use Livewire\Component;

class AttendanceCreate extends Component
{
    public $title = 'Certificate of Attendance Application';

    public $applications = [];

    public function mount(): void
    {
        $this->applications[] = [
            'type' => 'Biometric Malfunction',
            'reason' => '',
            'date' => '',
            'time' => '',
            'log_type' => 0,
        ];
    }

    public function addApplication()
    {
        $this->applications[] = [
            'type' => '',
            'reason' => '',
            'date' => '',
            'time' => '',
            'log_type' => 0,
        ];
    }

    public function store(Request $request)
    {
        $this->validate([
            'applications.*.reason' => 'required',
            'applications.*.date' => 'required',
            'applications.*.time' => 'required',
        ]);

        CreateAttendanceAction::run($this->applications, $request);

        return redirect()->route('portal.attendance');
    }

    public function render()
    {
        return view('livewire.portal.attendance.create')
            ->title($this->title)
            ->layout('layouts.portal');
    }
}
