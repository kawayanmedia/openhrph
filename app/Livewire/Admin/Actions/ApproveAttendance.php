<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Text;
use App\Services\Attendance\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ApproveAttendance extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Approve';

    public string $confirmText = 'Are you sure you want to approve this attendance request(s)?';

    public string $confirmButtonText = 'Approve Attendance';

    public function __construct($model = null)
    {
        parent::__construct($model);
    }

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $approver = $request->user()?->employee;
        $service = app(AttendanceCorrection::class);

        $applied = 0;
        $skipped = 0;
        $reasons = [];

        foreach ($models as $model) {
            $result = $service->apply($model, $approver, $fields['remarks'] ?? null);

            if ($result['status'] === AttendanceCorrection::STATUS_APPLIED) {
                $applied++;

                continue;
            }

            $skipped++;
            $reasons[] = $result['message'];
        }

        $summary = "Attendance approved: {$applied} applied";

        if ($skipped > 0) {
            $summary .= ", {$skipped} skipped (".implode(' ', array_unique($reasons)).')';
        }

        return self::message($summary.'.');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Text::make('Remarks', 'remarks')
                ->rules('required'),
        ];
    }
}
