<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\AttendanceScheduleAssignment;
use App\Models\AttendanceShift;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Effective-dated shift assignments and rotation cycles.
 *
 * A single shift applies from `effective_from`; a rotation is a cycle of shift
 * ids (0 = rest day) starting at `cycle_anchor`. Overlapping assignments are
 * not prevented in the UI yet, but the resolver always uses the latest
 * `effective_from`, so a new assignment supersedes an older one.
 */
class ShiftAssignment extends Resource
{
    public static string $model = AttendanceScheduleAssignment::class;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $with = ['employee', 'shift'];

    public static array $search = ['reason'];

    public static function label(): string
    {
        return 'Shift Assignments';
    }

    public function title(): string
    {
        $employee = $this->resource?->employee?->fullname;
        $shift = $this->resource?->shift?->name;

        return trim(($employee ?? '').' · '.($shift ?? 'Rotation'), ' ·');
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query->orderByDesc('effective_from');
    }

    public function fields(Request $request): array
    {
        return [
            BelongsTo::make('Employee', 'employee')
                ->options(Employee::employeeList())
                ->searchable()
                ->rules('required'),

            BelongsTo::make('Shift', 'shift')
                ->options(AttendanceShift::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),

            Text::make('Rotation', 'rotation')
                ->help('Optional cycle of shift ids, one per day, e.g. "1, 2, 0" where 0 is a rest day. Leave blank for a fixed shift.')
                ->resolveUsing(fn ($value): string => collect(is_array($value) ? $value : [])
                    ->map(fn ($id): string => $id === null ? 'Rest' : (string) $id)
                    ->implode(', '))
                ->fillUsing(function (Model $model, string $attribute, mixed $value): void {
                    $raw = trim((string) $value);

                    if ($raw === '') {
                        $model->setAttribute($attribute, null);

                        return;
                    }

                    $model->setAttribute(
                        $attribute,
                        collect(explode(',', $raw))
                            ->map(fn (string $piece): ?int => trim($piece) === '' || (int) trim($piece) === 0 ? null : (int) trim($piece))
                            ->all(),
                    );
                })
                ->onlyOnForms(),

            Text::make('Cycle', function () {
                return collect($this->rotation ?? [])
                    ->map(fn ($id): string => $id === null ? 'Rest' : '#'.$id)
                    ->implode(' → ');
            })->onlyOnIndex(),

            Date::make('Cycle Anchor', 'cycle_anchor')
                ->help('The first day of the rotation cycle.'),

            Text::make('Weekdays', 'weekdays')
                ->help('Optional ISO weekdays for a fixed shift: 1 = Monday, 7 = Sunday, e.g. "1, 2, 3, 4, 5".')
                ->resolveUsing(fn ($value): string => collect(is_array($value) ? $value : [])->implode(', '))
                ->fillUsing(fn (Model $model, string $attribute, mixed $value): mixed => $model->setAttribute(
                    $attribute,
                    collect(explode(',', (string) $value))->map(fn ($piece): int => (int) trim($piece))->filter()->values()->all() ?: null,
                ))
                ->onlyOnForms(),

            Text::make('Days', function () {
                return collect($this->weekdays ?? [])->implode(', ');
            })->onlyOnIndex(),

            Date::make('Effective From', 'effective_from')
                ->rules('required')
                ->sortable(),

            Date::make('Effective To', 'effective_to')->nullable(),

            Text::make('Reason', 'reason'),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [];
    }
}
