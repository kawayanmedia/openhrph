<?php

namespace App\Enums\Assistant;

use App\Models\LeaveRequest;
use App\Models\OfficialBusinessRequest;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAdjustmentRequest;
use App\Models\TimesheetRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The request kinds the assistant is allowed to read and (with confirmation) act on.
 */
enum RequestType: string
{
    case Leave = 'leave';
    case Schedule = 'schedule';
    case Overtime = 'overtime';
    case Undertime = 'undertime';
    case Attendance = 'attendance';
    case OfficialBusiness = 'official_business';

    public function label(): string
    {
        return match ($this) {
            self::Leave => 'Leave request',
            self::Schedule => 'Shift adjustment request',
            self::Overtime => 'Overtime request',
            self::Undertime => 'Undertime request',
            self::Attendance => 'Attendance request',
            self::OfficialBusiness => 'Official business request',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Leave => 'leave requests',
            self::Schedule => 'shift adjustment requests',
            self::Overtime => 'overtime requests',
            self::Undertime => 'undertime requests',
            self::Attendance => 'attendance requests',
            self::OfficialBusiness => 'official business requests',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Leave => LeaveRequest::class,
            self::Schedule => ScheduleAdjustmentRequest::class,
            self::Overtime, self::Undertime => OvertimeRequest::class,
            self::Attendance => TimesheetRequest::class,
            self::OfficialBusiness => OfficialBusinessRequest::class,
        };
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $query = $this->model()::query();

        return match ($this) {
            self::Overtime => $query->where('type', OvertimeRequest::TYPE_OVERTIME),
            self::Undertime => $query->where('type', OvertimeRequest::TYPE_UNDERTIME),
            default => $query,
        };
    }

    /**
     * A short, human readable description of a single request row.
     */
    public function summary(Model $request): string
    {
        return match ($this) {
            self::Leave => sprintf(
                '%s to %s%s',
                $request->date_from?->format('M j, Y') ?? '—',
                $request->date_to?->format('M j, Y') ?? '—',
                $request->reason ? ' · '.$request->reason : '',
            ),
            self::Schedule => sprintf(
                'shift %s to %s%s',
                $request->date_from?->format('M j, Y') ?? '—',
                $request->date_to?->format('M j, Y') ?? '—',
                $request->time_from && $request->time_to
                    ? ' · '.$request->time_from->format('g:i A').'–'.$request->time_to->format('g:i A')
                    : '',
            ),
            self::Overtime, self::Undertime => sprintf(
                '%s · %s hour%s%s',
                $request->date?->format('M j, Y') ?? '—',
                rtrim(rtrim((string) $request->hours, '0'), '.') ?: '0',
                (float) $request->hours === 1.0 ? '' : 's',
                $request->reason ? ' · '.$request->reason : '',
            ),
            self::Attendance => sprintf(
                '%s · %s%s',
                $request->log_date?->format('M j, Y') ?? '—',
                $request->log_type == 0 ? 'time in' : 'time out',
                $request->time ? ' at '.substr((string) $request->time, 0, 5) : '',
            ),
            self::OfficialBusiness => sprintf(
                '%s to %s%s',
                $request->date_from?->format('M j, Y') ?? '—',
                $request->date_to?->format('M j, Y') ?? '—',
                $request->location ? ' · '.$request->location : '',
            ),
        };
    }

    /**
     * The attributes surfaced to the model and to the confirmation cards.
     *
     * @return array<string, mixed>
     */
    public function detail(Model $request): array
    {
        return [
            'id' => $request->getKey(),
            'employee' => $request->employee?->fullname,
            'employee_number' => $request->employee?->employee_number,
            'status' => $request->status,
            'summary' => self::summary($request),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
