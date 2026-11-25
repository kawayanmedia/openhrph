<?php

namespace App\Jurisdictions\Philippines\Private;

use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Fields\Text;

/**
 * Employee identity fields for a private establishment.
 */
final class EmployeeFields
{
    /**
     * @return array<int, Field>
     */
    public static function fields(): array
    {
        return [
            Text::make('SSS Number', 'sss_number')
                ->rules('nullable', 'regex:/^\d{2}-?\d{7}-?\d$/')
                ->withMeta(['extraAttributes' => ['placeholder' => '00-0000000-0']])
                ->help('Required for SSS remittances.')
                ->hideFromIndex(),
        ];
    }
}
