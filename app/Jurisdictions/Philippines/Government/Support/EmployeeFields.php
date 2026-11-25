<?php

namespace App\Jurisdictions\Philippines\Government\Support;

use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Fields\Text;

/**
 * Employee identity fields that only a government installation records.
 */
final class EmployeeFields
{
    /**
     * @return array<int, Field>
     */
    public static function fields(): array
    {
        return [
            Text::make('GSIS BP Number', 'gsis_number')
                ->rules('nullable', 'regex:/^[\d\s-]{8,24}$/')
                ->withMeta(['extraAttributes' => ['placeholder' => '0000-0000-0000']])
                ->help('Required for GSIS remittances.')
                ->hideFromIndex(),

            Text::make('Salary Grade', 'salary_grade')
                ->rules('nullable', 'integer', 'between:1,33')
                ->hideFromIndex(),

            Text::make('Step', 'step')
                ->rules('nullable', 'integer', 'between:1,8')
                ->hideFromIndex(),

            Text::make('Item Number', 'item_number')
                ->rules('nullable', 'max:64')
                ->hideFromIndex(),
        ];
    }
}
