<?php

namespace App\Livewire\Admin\Actions;

use App\Actions\SetTimesheetAbsent;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Models\Office;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MarkTimesheetAbsent extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Mark Missing Days Absent';

    public string $confirmText = 'Mark every scheduled working day without attendance in this range as absent?';

    public string $confirmButtonText = 'Mark Absent';

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $from = (string) $fields['start_date'];
        $to = (string) $fields['end_date'];

        if ($from > $to) {
            throw ValidationException::withMessages([
                'actionFields.end_date' => 'The end date must be on or after the start date.',
            ]);
        }

        $officeId = $fields['office_id'] ?? null;
        $officeId = $officeId === '' || $officeId === null ? null : (int) $officeId;

        $count = SetTimesheetAbsent::run($from, $to, $officeId);

        return self::message("{$count} day(s) marked absent.");
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Date::make('Start Date', 'start_date')->rules('required'),

            Date::make('End Date', 'end_date')->rules('required'),

            Select::make('Office', 'office_id')
                ->options(['' => 'All offices'] + Office::officesList())
                ->nullable(),
        ];
    }
}
