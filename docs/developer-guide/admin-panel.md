# Admin panel

The first-party admin lives at `/app` (configured by `ADMIN_PATH` in `.env`, defaulting to `app` in `config/admin.php`). It is a Livewire application with its own resource, field, filter, action, metric, and lens classes. It does not use Filament classes.

## Access and routing

`routes/admin.php`:

- `GET /app/login` uses the Livewire `Login` component and the `guest:web` middleware.
- Authenticated routes use `web`, `auth`, and `can:viewAdmin`.
- The default gate is `viewAdmin`, configured by `config/admin.php` (`gate`).
- The route prefix is `config('admin.path')`, which reads `ADMIN_PATH` and defaults to `app` (`/app`).

Menu visibility comes from `config/admin.php` and `App\Livewire\Admin\Support\Navigation`. A company owner sees everything; other users see entries their group permissions allow. `ADMIN_ALLOW_ALL=true` is an emergency escape hatch, not a normal configuration.

## Add a resource

Create a class in `app/Livewire/Admin/Resources`. It is discovered automatically by `ResourceRegistry`.

```php
<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class Office extends Resource
{
    public static string $model = \App\Models\Office::class;

    public static string $title = 'id';

    public static int $priority = 2;

    public static string $group = 'Admin';

    public static array $search = ['id', 'name'];

    public static function label(): string
    {
        return 'Office';
    }

    public static function pluralLabel(): string
    {
        return 'Offices / Locations';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Code')->sortable(),
            Text::make('Name')->sortable()->rules('required'),
        ];
    }
}
```

Add the menu entry in `config/admin.php`:

```php
['type' => 'resource', 'key' => 'office', 'label' => 'Office'],
```

The URI key defaults to the kebab-case class name (`office`, `pay-roll-group`, and so on). Override it with `Resource::uriKey()` if needed.

### Optional resource hooks

| Method | Purpose |
| --- | --- |
| `indexQuery(Builder $query)` | Base query for the index |
| `detailQuery(Builder $query)` | Base query for the detail page |
| `newModel()` | Override model creation |
| `indexFields(Request $request)` | Columns shown in the index |
| `detailFields(Request $request)` | Fields shown on the detail page |
| `formFields(Request $request, bool $creating)` | Fields shown on create/edit |
| `cards(Request $request)` | Metric cards above the index |
| `filters(Request $request)` | Index filters |
| `actions(Request $request)` | Row and toolbar actions |
| `lenses(Request $request)` | Alternate index queries |
| `afterCreate/afterUpdate(Request, Model)` | Post-save hooks |
| `redirectAfterCreate/Update(Request, Model)` | Custom redirects |

## Fields

Fields live in `app/Livewire/Admin/Fields`. The base `Field` class exposes:

```php
Text::make('Name', 'name')
    ->rules('required', 'max:255')
    ->help('Shown on the office list')
    ->placeholder('Head Office')
    ->sortable()
    ->searchable()
    ->filterable();

Text::make('Notes')
    ->nullable()
    ->hideFromIndex()
    ->onlyOnForms();
```

Common methods:

| Method | Effect |
| --- | --- |
| `make($name, $attribute = null)` | Create the field; a closure attribute creates a computed field |
| `rules(...$rules)` | Validation rules |
| `creationRules(...$rules)` / `updateRules(...$rules)` | Create/update-specific rules |
| `nullable()` | Adds `nullable` unless `required` is present |
| `help($text)` | Help text below the control |
| `placeholder($text)` | Placeholder for inputs |
| `default($value)` | Default form value |
| `sortable()` | Sortable index column |
| `searchable()` | Include in search |
| `filterable()` | Offer as a filter |
| `readonly()` | Render and submit the value but avoid unnecessary writes |
| `showOnIndex()` / `hideFromIndex()` | Index visibility |
| `showOnDetail()` / `hideFromDetail()` | Detail visibility |
| `hideWhenCreating()` / `hideWhenUpdating()` | Form visibility |
| `onlyOnIndex()` / `onlyOnDetail()` / `onlyOnForms()` | Shortcut visibility |
| `dependsOn([...], fn ($data) => ...)` | Conditional behavior; marks the field as live |
| `displayUsing(fn ($value) => ...)` | Display formatting |
| `resolveUsing(fn ($value) => ...)` | Resolve a display value |
| `fillUsing(fn ($model, $value) => ...)` | Custom fill behavior |
| `asHtml()` | Render the display value as HTML |
| `withMeta([...])` | Extra metadata for the field partial |

Field types include: `Text`, `Textarea`, `Number`, `Currency`, `Date`, `DateTime`, `Select`, `BelongsTo`, `Boolean`, `BooleanGroup`, `Password`, `ID`, `File`, `Files`, `Trix`, `Badge`, `Status`, `Stack`, `Line`, `Heading`, and `Gravatar`.

The Blade partial for each type lives in `resources/views/livewire/admin/fields`. The shared wrapper is `resources/views/components/admin/field.blade.php`. Use the `ui-*` classes from `resources/css/app.css` so light/dark, focus, disabled, and error states stay consistent.

## Validation

Validation runs in `AdminForm::save()` using each field's `validationRules()`. Required fields show an asterisk through `Field::isRequired()`. Error messages are keyed by `form.<attribute>`; action modals use `actionFields.<attribute>`.

When you add a field with a rule, add a test that submits invalid data and asserts `assertHasErrors(['form.attribute' => 'required'])`.

## Filters

Create a filter in `app/Livewire/Admin/Filters`:

```php
class OfficeFilter extends Filter
{
    public string $name = 'Office';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query->where('office_id', $value);
    }

    public function options(Request $request): array
    {
        return Office::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
```

Set `$type = 'date'`, `'boolean'`, or `'multi'` to render a different control. Register the filter in the resource's `filters()` method.

## Actions

Create an action in `app/Livewire/Admin/Actions`:

```php
class ApproveLeave extends Action
{
    public string $name = 'Approve Leave';

    public string $confirmText = 'Are you sure you want to approve this leave?';

    public string $confirmButtonText = 'Approve Leave';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
            $model->forceFill([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ])->save();
        }

        return static::message('Leave approved.');
    }
}
```

Use `fields()` to add a modal form. Use `onlyOnIndex()`, `onlyOnDetail()`, `onlyOnTableRow()`, or `onlyOnIndexToolbar()` to control placement.

## Metrics and lenses

- Extend `App\Livewire\Admin\Metrics\Metric` and implement `calculate(Request $request)`.
- Extend `App\Livewire\Admin\Lenses\Lens` and implement `query(Request $request, Builder $query)`.

Register metrics in `cards()` and lenses in `lenses()` on the resource.

## Authorization

Resources can use Laravel policies:

- `Resource::authorize($ability, $arguments)` checks the policy when one exists.
- `Resource::allows($ability, $arguments)` returns a boolean for UI decisions.
- `canCreateResource()` and `canDeleteResource()` provide resource-level overrides.
- Policies live in `app/Policies` and use the standard Laravel ability names.

Always enforce authorization on the server. Hiding a button is not enough.

## Testing a new resource

Add a Livewire test for the create/update/delete flow and an authorization test for another company:

```php
Livewire::test(AdminForm::class, ['resource' => 'office'])
    ->set('form.code', 'HQ')
    ->set('form.name', 'Head Office')
    ->call('save')
    ->assertHasNoErrors();

$this->assertDatabaseHas('offices', ['code' => 'HQ', 'name' => 'Head Office']);
```

See [Testing](testing.md) and the existing `tests/Feature/Admin/AdminEngineTest.php` for reusable patterns.
