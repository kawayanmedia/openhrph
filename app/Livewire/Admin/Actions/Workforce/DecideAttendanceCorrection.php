<?php

namespace App\Livewire\Admin\Actions\Workforce;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Services\Attendance\AttendanceCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DecideAttendanceCorrection extends Action
{
    public string $name = 'Review Correction';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Action', 'action')
                ->options([
                    'approve' => 'approve',
                    'reject' => 'reject',
                    'apply' => 'apply approved correction',
                ])
                ->rules('required'),
            Textarea::make('Comments', 'comments'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(AttendanceCorrectionService::class);
        $action = (string) $fields['action'];
        $comments = $fields['comments'] ?? null;

        foreach ($models as $model) {
            match ($action) {
                'approve' => $service->approve($model, $request->user(), $comments),
                'reject' => $service->reject($model, $request->user(), (string) $comments),
                'apply' => $service->apply($model, $request->user()),
            };
        }

        return self::message('Attendance correction updated.');
    }
}
