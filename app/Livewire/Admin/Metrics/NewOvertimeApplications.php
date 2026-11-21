<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\OvertimeRequest;
use Illuminate\Http\Request;

class NewOvertimeApplications extends Metric
{
    public string $name = 'New Overtime Applications';

    public function calculate(Request $request): mixed
    {
        return OvertimeRequest::query()
            ->with('employee')
            ->where('status', 'Pending')
            ->latest('date')
            ->limit(5)
            ->get()
            ->map(fn (OvertimeRequest $row): array => [
                'title' => trim(($row->employee?->first_name ?? '').' '.($row->employee?->last_name ?? '')),
                'subtitle' => 'Overtime · '.optional($row->date)->format('M d'),
            ])
            ->all();
    }

    public function component(): string
    {
        return 'table';
    }
}
