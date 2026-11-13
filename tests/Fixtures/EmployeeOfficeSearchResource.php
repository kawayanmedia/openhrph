<?php

namespace Tests\Fixtures;

use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeOfficeSearchResource extends Resource
{
    public static string $model = Employee::class;

    public static array $search = ['office.name'];

    public function fields(Request $request): array
    {
        return [];
    }
}
