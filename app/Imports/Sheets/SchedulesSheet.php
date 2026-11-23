<?php

namespace App\Imports\Sheets;

use App\Imports\Workbook\Column;
use App\Imports\Workbook\ColumnType;
use App\Imports\Workbook\ReferenceIndex;
use App\Imports\Workbook\SheetDefinition;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Model;

class SchedulesSheet extends SheetDefinition
{
    public function key(): string
    {
        return 'schedules';
    }

    public function title(): string
    {
        return 'Schedules';
    }

    public function aliases(): array
    {
        return ['Schedule'];
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return Schedule::class;
    }

    public function keyColumns(): array
    {
        return ['name'];
    }

    public function columns(): array
    {
        return [
            new Column(
                key: 'name',
                heading: 'Name',
                type: ColumnType::Text,
                required: true,
                rules: ['max:128'],
                aliases: ['Schedule'],
                example: 'Day Shift',
            ),
            new Column(
                key: 'time_in',
                heading: 'Time In',
                type: ColumnType::Time,
                required: true,
                rules: ['date_format:H:i'],
                example: '08:00',
                help: '24-hour time, for example 08:00.',
            ),
            new Column(
                key: 'break_time_start',
                heading: 'Break Start',
                type: ColumnType::Time,
                rules: ['date_format:H:i'],
                example: '12:00',
            ),
            new Column(
                key: 'break_time_end',
                heading: 'Break End',
                type: ColumnType::Time,
                rules: ['date_format:H:i'],
                example: '13:00',
            ),
            new Column(
                key: 'time_out',
                heading: 'Time Out',
                type: ColumnType::Time,
                required: true,
                rules: ['date_format:H:i'],
                example: '17:00',
            ),
            new Column(
                key: 'is_default',
                heading: 'Default',
                type: ColumnType::Boolean,
                options: ['Yes', 'No'],
                example: 'Yes',
                help: 'The default schedule is assigned to new employees.',
            ),
            new Column(
                key: 'is_active',
                heading: 'Active',
                type: ColumnType::Boolean,
                options: ['Yes', 'No'],
                example: 'Yes',
            ),
        ];
    }

    public function sampleRows(): array
    {
        return [
            [
                'name' => 'Day Shift',
                'time_in' => '08:00',
                'break_time_start' => '12:00',
                'break_time_end' => '13:00',
                'time_out' => '17:00',
                'is_default' => 'Yes',
                'is_active' => 'Yes',
            ],
        ];
    }

    public function validateValues(array $values, int $row, callable $add): void
    {
        $breakStart = $values['break_time_start'] ?? null;
        $breakEnd = $values['break_time_end'] ?? null;

        if (($breakStart === null) !== ($breakEnd === null)) {
            $add(
                $breakStart === null ? 'break_time_start' : 'break_time_end',
                'Enter both the break start and the break end, or leave both blank.',
            );
        }

        if ($breakStart !== null && $breakEnd !== null && $breakStart >= $breakEnd) {
            $add('break_time_end', 'Break End must be later than Break Start.');
        }

        if (($values['time_in'] ?? null) === ($values['time_out'] ?? null)) {
            $add('time_out', 'Time Out must be different from Time In.');
        }
    }

    public function fill(Model $model, array $values, ReferenceIndex $references, bool $creating): void
    {
        if ($creating) {
            $model->setAttribute('is_default', false);
            $model->setAttribute('is_active', true);
        }

        $model->setAttribute('name', $values['name']);

        // The admin form stores one time-in/time-out pair and derives the
        // morning/afternoon columns the attendance engine reads.
        foreach (['am_in', 'am_out', 'pm_in', 'pm_out', 'am_out_morning'] as $attribute) {
            $model->setAttribute($attribute, null);
        }

        $timeIn = $values['time_in'];
        $timeOut = $values['time_out'];

        if ($timeIn > '12:00') {
            $model->setAttribute('pm_in', $timeIn);
        } else {
            $model->setAttribute('am_in', $timeIn);
        }

        if ($timeOut > '12:00') {
            $model->setAttribute('pm_out', $timeOut);
        } else {
            $model->setAttribute('am_out', $timeOut);
            $model->setAttribute('am_out_morning', $timeOut);
        }

        if (array_key_exists('break_time_start', $values)) {
            $model->setAttribute('break_time_start', $values['break_time_start']);
        }

        if (array_key_exists('break_time_end', $values)) {
            $model->setAttribute('break_time_end', $values['break_time_end']);
        }

        if (array_key_exists('is_default', $values)) {
            $model->setAttribute('is_default', $values['is_default']);
        }

        if (array_key_exists('is_active', $values)) {
            $model->setAttribute('is_active', $values['is_active']);
        }
    }

    public function afterSave(Model $model, array $values, bool $creating): void
    {
        if (! $model->getAttribute('is_default')) {
            return;
        }

        Schedule::query()->whereKeyNot($model->getKey())->update(['is_default' => false]);
    }
}
