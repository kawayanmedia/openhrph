<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\AttendanceShift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class Shift extends Resource
{
    public static string $model = AttendanceShift::class;

    public static int $priority = 6;

    public static string $title = 'name';

    public static string $group = 'Attendance';

    public static array $search = ['name', 'code'];

    public static function label(): string
    {
        return 'Shifts';
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Text::make('Code', 'code')
                ->sortable(),

            Select::make('Type', 'type')
                ->options([
                    AttendanceShift::TYPE_FIXED => 'Fixed',
                    AttendanceShift::TYPE_SPLIT => 'Split',
                    AttendanceShift::TYPE_OVERNIGHT => 'Overnight',
                    AttendanceShift::TYPE_FLEXIBLE => 'Flexible',
                ])
                ->default(AttendanceShift::TYPE_FIXED)
                ->rules('required'),

            Text::make('Segments', 'segments')
                ->help('Working spans, e.g. "08:00 - 12:00, 13:00 - 17:00". A span that ends before it starts crosses midnight.')
                ->rules('required')
                ->resolveUsing(fn ($value): string => $this->segmentsText($value))
                ->fillUsing(fn (Model $model, string $attribute, mixed $value): mixed => $model->setAttribute($attribute, $this->segments($value)))
                ->onlyOnForms(),

            Text::make('Breaks', 'breaks')
                ->help('Unpaid breaks, e.g. "12:00 - 13:00". Leave blank when the segments already exclude the break.')
                ->nullable()
                ->resolveUsing(fn ($value): string => $this->segmentsText($value))
                ->fillUsing(fn (Model $model, string $attribute, mixed $value): mixed => $model->setAttribute($attribute, $this->segments($value) ?: null))
                ->onlyOnForms(),

            Text::make('Working spans', 'segments_text')
                ->onlyOnIndex(),

            Text::make('Breaks', 'breaks_text')
                ->onlyOnIndex(),

            Text::make('Night Window Start', 'night_window_start')
                ->nullable()
                ->placeholder('22:00')
                ->help('Leave blank to use the company/sector setting.'),

            Text::make('Night Window End', 'night_window_end')
                ->nullable()
                ->placeholder('06:00'),

            Number::make('Grace Period', 'grace_minutes')
                ->help('Minutes before an arrival is late.')
                ->default(0),

            Boolean::make('Default Shift', 'is_default')
                ->default(false),

            Boolean::make('Active', 'is_active')
                ->default(true),
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

    public static function afterCreate(Request $request, Model $model): void
    {
        static::promoteDefault($model);
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        static::promoteDefault($model);
    }

    private static function promoteDefault(Model $model): void
    {
        if (! (bool) $model->getAttribute('is_default')) {
            return;
        }

        AttendanceShift::query()
            ->whereKeyNot($model->getKey())
            ->update(['is_default' => false]);
    }

    private function segments(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return collect(explode(',', (string) $value))
            ->map(function (string $piece): ?array {
                $piece = trim($piece);

                if ($piece === '') {
                    return null;
                }

                [$start, $end] = array_pad(preg_split('/\s*-\s*/', $piece, 2) ?: [], 2, null);

                return $start !== null && $end !== null ? [$start, $end] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function segmentsText(mixed $value): string
    {
        return collect(is_array($value) ? $value : Arr::wrap($value))
            ->filter(fn ($segment): bool => is_array($segment) && count($segment) >= 2)
            ->map(fn (array $segment): string => $segment[0].' - '.$segment[1])
            ->implode(', ');
    }
}
