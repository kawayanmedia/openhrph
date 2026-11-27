<?php

namespace App\Livewire\Admin\Reports;

use App\Services\Analytics\WorkforceAnalytics as WorkforceAnalyticsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class WorkforceAnalytics extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $summary = [];

    public function mount(WorkforceAnalyticsService $analytics): void
    {
        $this->summary = $analytics->summary();
    }

    public function refreshMetrics(WorkforceAnalyticsService $analytics): void
    {
        $this->summary = $analytics->summary();
    }

    public function render()
    {
        return view('livewire.admin.reports.workforce-analytics')
            ->title('Workforce Analytics');
    }
}
