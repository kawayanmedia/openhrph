<?php

namespace Tests\Fixtures;

use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Http\Request;

class DottedAttributeResource extends Resource
{
    public static string $model = Employee::class;

    public function fields(Request $request): array
    {
        return [
            Text::make('Employee No.', 'employee.employee_number'),
            Text::make('First Name', 'first_name'),
        ];
    }
}
