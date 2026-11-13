<?php

namespace App\Livewire\Portal\Timesheets;

use App\Models\Employee;
use App\Models\Timesheet;
use App\Services\Attendance\AttendancePeriod;
use App\Services\Attendance\TimesheetLogRecorder;
use Carbon\CarbonImmutable;
use Livewire\Component;
use Throwable;

class TimesheetPunch extends Component
{
    public string $title = 'My Timesheet';

    /** @var array<string, mixed>|null */
    public ?array $today = null;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public ?string $status = null;

    public function mount(): void
    {
        $this->load();
    }

    public function timeIn(): void
    {
        $this->punch(0);
    }

    public function timeOut(): void
    {
        $this->punch(1);
    }

    public function punch(int $logType): void
    {
        $this->status = null;
        $this->resetErrorBag('punch');

        $employee = $this->employee();

        if ($employee === null) {
            $this->addError('punch', 'Your account is not linked to an employee record.');

            return;
        }

        $recorder = app(TimesheetLogRecorder::class);
        $period = app(AttendancePeriod::class);
        $moment = CarbonImmutable::now();

        $timesheet = $recorder->findFor($employee, $moment);

        $canRecord = $timesheet === null
            ? ! $period->isClosedFor($employee, $moment)
            : $recorder->canRecord($employee, $timesheet);

        if (! $canRecord) {
            $this->addError('punch', 'Attendance for this payroll period is closed.');

            return;
        }

        if ($logType === 1 && ($timesheet === null || $recorder->nextLogType($timesheet) === 0)) {
            $this->addError('punch', 'Record a time in before timing out.');

            return;
        }

        try {
            $result = $recorder->record($employee, $moment, 'portal', null, $logType);
        } catch (Throwable $exception) {
            report($exception);

            $this->addError('punch', 'Unable to record the punch. Please try again.');

            return;
        }

        $this->status = $result['status'] === TimesheetLogRecorder::STATUS_ACCEPTED
            ? ($result['log_type'] === 0 ? 'Time in recorded at ' : 'Time out recorded at ').$moment->format('h:i A').'.'
            : 'That punch was already recorded.';

        $this->load();
    }

    public function render()
    {
        return view('livewire.portal.timesheets.punch')
            ->title($this->title)
            ->layout('layouts.portal');
    }

    private function load(): void
    {
        $employee = $this->employee();

        if ($employee === null) {
            $this->today = null;
            $this->rows = [];

            return;
        }

        $recorder = app(TimesheetLogRecorder::class);
        $period = app(AttendancePeriod::class);
        $today = CarbonImmutable::now();

        $timesheet = $recorder->findFor($employee, $today);
        $window = $period->windowFor($employee, $today);

        $this->today = [
            'log_date' => $today->format('M d, Y'),
            'schedule' => $timesheet?->schedule?->name ?? $employee->schedule?->name,
            'time_in' => $timesheet?->time_in,
            'time_out' => $timesheet?->time_out,
            'total_hours' => number_format((float) ($timesheet?->total_hours ?? 0), 2),
            'next' => $timesheet === null ? 0 : $recorder->nextLogType($timesheet),
            'can_record' => $timesheet === null
                ? ! $period->isClosedFor($employee, $today)
                : $recorder->canRecord($employee, $timesheet),
            'locked' => (bool) ($timesheet?->is_locked ?? false),
            'period' => $window['label'],
            'logs' => collect($timesheet?->logs ?? [])
                ->map(fn (array $log): array => [
                    'time' => $log['time'] ?? null,
                    'label' => ((int) ($log['log_type'] ?? 0)) === 0 ? 'In' : 'Out',
                    'source' => $log['source'] ?? 'biometric',
                ])
                ->values()
                ->all(),
        ];

        $this->rows = Timesheet::query()
            ->where('employee_id', $employee->getKey())
            ->whereBetween('log_date', [$window['start']->toDateString(), $window['end']->toDateString()])
            ->orderByDesc('log_date')
            ->get()
            ->map(fn (Timesheet $row): array => [
                'date' => $row->log_date?->format('M d, Y'),
                'day_type' => $row->day_type,
                'holiday' => $row->holiday_name,
                'time_in' => $row->time_in,
                'time_out' => $row->time_out,
                'hours' => number_format((float) $row->total_hours, 2),
                'logs' => count($row->logs ?? []),
            ])
            ->all();
    }

    private function employee(): ?Employee
    {
        return request()->user()?->employee;
    }
}
