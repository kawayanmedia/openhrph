<?php

namespace App\Livewire\Portal;

use App\Models\DevelopmentPlan;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceService;
use Livewire\Component;

class Performance extends Component
{
    public string $title = 'My Performance';

    public $employee;

    public $reviews;

    public $goals;

    public $plans;

    public function mount(): void
    {
        $this->employee = portal_user()?->employee;

        $this->load();
    }

    public function acknowledge(int $reviewId, PerformanceService $service): void
    {
        $review = PerformanceReview::query()
            ->where('employee_id', $this->employee?->getKey())
            ->findOrFail($reviewId);

        $service->acknowledge($review, portal_user());

        $this->load();

        session()->flash('status', 'Review acknowledged.');
    }

    protected function load(): void
    {
        $employeeId = $this->employee?->getKey();

        $this->reviews = PerformanceReview::query()
            ->with('cycle', 'reviewer')
            ->where('employee_id', $employeeId)
            ->latest()
            ->get();

        $this->goals = PerformanceGoal::query()
            ->with('cycle')
            ->where('employee_id', $employeeId)
            ->latest()
            ->get();

        $this->plans = DevelopmentPlan::query()
            ->with('cycle')
            ->where('employee_id', $employeeId)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.performance.index')
            ->title($this->title)
            ->layout('layouts.portal');
    }
}
