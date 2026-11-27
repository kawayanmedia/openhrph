<?php

namespace App\Livewire\Admin\Actions\Workforce;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Services\Attendance\TimesheetApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManageTimesheetApproval extends Action
{
    public string $name = 'Approval Workflow';

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
                    'submit' => 'submit',
                    'approve' => 'approve',
                    'reject' => 'reject',
                    'lock' => 'lock for payroll',
                ])
                ->rules('required'),
            Textarea::make('Comments', 'comments'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(TimesheetApprovalService::class);
        $action = (string) $fields['action'];
        $comments = $fields['comments'] ?? null;

        foreach ($models as $model) {
            match ($action) {
                'submit' => $service->submit($model, $request->user()),
                'approve' => $service->approve($model, $request->user(), $comments),
                'reject' => $service->reject($model, $request->user(), (string) $comments),
                'lock' => $service->lock($model, $request->user(), $comments),
            };
        }

        return self::message('Timesheet approval updated.');
    }
}
