<?php

namespace App\Livewire\Portal;

use App\Models\EmployeeCertification;
use App\Models\TrainingEnrollment;
use Livewire\Component;

class Training extends Component
{
    public string $title = 'My Training';

    public $employee;

    public $enrollments;

    public $certifications;

    public function mount(): void
    {
        $this->employee = portal_user()?->employee;

        $employeeId = $this->employee?->getKey();

        $this->enrollments = TrainingEnrollment::query()
            ->with('session.course')
            ->where('employee_id', $employeeId)
            ->latest()
            ->get();

        $this->certifications = EmployeeCertification::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('issued_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.training.index')
            ->title($this->title)
            ->layout('layouts.portal');
    }
}
