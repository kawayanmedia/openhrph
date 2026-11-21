<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class NewLeaveApplications extends Metric
{
    public string $name = 'New Leave Applications';

    public function calculate(Request $request): mixed
    {
        return LeaveRequest::query()
            ->with(['employee', 'leave'])
            ->where('status', LeaveRequest::PENDING)
            ->latest('date_filed')
            ->limit(5)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'title' => trim(($leave->employee?->first_name ?? '').' '.($leave->employee?->last_name ?? '')),
                'subtitle' => trim(($leave->leave?->name ?? 'Leave').' · '.optional($leave->date_from)->format('M d').'–'.optional($leave->date_to)->format('M d')),
            ])
            ->all();
    }

    public function component(): string
    {
        return 'table';
    }
}
