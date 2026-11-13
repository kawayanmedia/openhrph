<?php

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\BooleanGroup;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Fields\Line;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resources\Allowance;
use App\Livewire\Admin\Support\FormData;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TimesheetRequest;
use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\DottedAttributeResource;

it('resolves the belongs-to foreign key from the model relationship', function () {
    $field = BelongsTo::make('Department', 'department');
    $model = Position::factory()->make(['company_id' => 1]);

    expect($field->foreignKeyName($model))->toBe('department_id');

    $field->fill($model, 7, 'department');

    expect($model->getAttributes())->toMatchArray(['department_id' => 7]);
});

it('formats dates for html date inputs and display', function () {
    $model = new class extends Model
    {
        protected $guarded = [];

        protected $casts = ['hired_at' => 'date'];
    };

    $model->hired_at = '2024-03-05 13:45:00';

    $field = Date::make('Hired at', 'hired_at');

    expect($field->resolveForForm($model))->toBe('2024-03-05')
        ->and($field->resolveForDisplay($model))->toBe('2024-03-05');
});

it('applies dependsOn callbacks to change field state', function () {
    $resource = new Allowance;
    $fields = $resource->fields(request());

    $amount = collect($fields)->first(fn (Field $field) => $field->attribute === 'amount');

    expect($amount)->toBeInstanceOf(Currency::class)
        ->and($amount->readonly)->toBeTrue();

    $amount->applyDependencies(new FormData(['tax_option' => 'partially_nontaxable']));

    expect($amount->readonly)->toBeFalse()
        ->and($amount->rules)->toContain('required');
});

it('stores boolean groups as a truthy map preserving unknown keys', function () {
    $field = BooleanGroup::make('Permissions', 'permissions')
        ->options(['Offices' => 'Offices', 'Employees' => 'Employees']);

    $model = new class extends Model
    {
        protected $guarded = [];

        protected $casts = ['permissions' => 'array'];
    };

    $field->fill($model, [
        'Offices' => true,
        'Employees' => false,
        'Legacy' => true,
    ], 'permissions');

    expect($model->permissions)->toBe(['Offices' => true, 'Legacy' => true]);
});

it('treats relation path attributes as display only', function () {
    $field = Text::make('Employee No.', 'employee.employee_number');

    expect($field->isFormInput())->toBeFalse()
        ->and($field->canFillFromForm())->toBeFalse();
});

it('resolves relation path attributes for display', function () {
    $request = relationPathRequest();

    $field = Text::make('Employee No.', 'employee.employee_number');

    expect($field->resolveForDisplay($request))->toBe('E-100');
});

it('resolves relation path attributes inside stack lines', function () {
    $request = relationPathRequest();

    $line = Line::make('Employee No.', 'employee.employee_number');

    expect($line->resolveForDisplay($request))->toBe('E-100');
});

it('shows belongs-to relations on the index when enabled', function () {
    $request = relationPathRequest();

    $field = BelongsTo::make('Employee', 'employee')->showOnIndex();

    expect($field->showOnIndex)->toBeTrue()
        ->and($field->resolveForDisplay($request))->toBe('Doe, Jane');
});

function relationPathRequest(): TimesheetRequest
{
    $employee = Employee::factory()->make([
        'company_id' => 1,
        'office_id' => null,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'employee_number' => 'E-100',
    ]);

    $request = TimesheetRequest::factory()->make(['company_id' => 1, 'employee_id' => 1]);
    $request->setRelation('employee', $employee);

    return $request;
}

it('omits relation path attributes from form fields', function () {
    $fields = (new DottedAttributeResource)->formFields(request(), true);

    expect(array_map(fn ($field) => $field->attribute, $fields))->toBe(['first_name']);
});
