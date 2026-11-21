<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\ScheduleAdjustmentRequest;
use Illuminate\Http\Request;

class NewScheduleApplications extends Metric
{
    public string $name = 'New Schedule Applications';

    public function calculate(Request $request): mixed
    {
        return ScheduleAdjustmentRequest::query()
            ->with('employee')
            ->where('status', 'Pending')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ScheduleAdjustmentRequest $row): array => [
                'title' => trim(($row->employee?->first_name ?? '').' '.($row->employee?->last_name ?? '')),
                'subtitle' => 'Schedule adjustment',
            ])
            ->all();
    }

    public function component(): string
    {
        return 'table';
    }
}
