<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\TimesheetRequest;
use Illuminate\Http\Request;

class NewAttendanceApplications extends Metric
{
    public string $name = 'New Attendance Applications';

    public function calculate(Request $request): mixed
    {
        return TimesheetRequest::query()
            ->with('employee')
            ->where('status', 'Pending')
            ->latest('date_filed')
            ->limit(5)
            ->get()
            ->map(fn (TimesheetRequest $row): array => [
                'title' => trim(($row->employee?->first_name ?? '').' '.($row->employee?->last_name ?? '')),
                'subtitle' => trim(($row->type ?? 'Correction').' · '.optional($row->log_date)->format('M d')),
            ])
            ->all();
    }

    public function component(): string
    {
        return 'table';
    }
}
