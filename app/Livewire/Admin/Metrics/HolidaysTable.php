<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidaysTable extends Metric
{
    public string $name = 'Upcoming Holidays';

    public function calculate(Request $request): mixed
    {
        return Holiday::query()
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(5)
            ->get()
            ->map(fn (Holiday $holiday): array => [
                'title' => (string) $holiday->name,
                'subtitle' => optional($holiday->date)->format('M d, Y').' · '.str_replace('_', ' ', (string) $holiday->holiday_type),
            ])
            ->all();
    }

    public function component(): string
    {
        return 'table';
    }
}
