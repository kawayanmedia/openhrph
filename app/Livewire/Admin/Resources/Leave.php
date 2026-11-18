<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\OverrideLeaveType;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Leave as LeaveModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Leave extends Resource
{
    public static string $model = LeaveModel::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Leave Management';

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Leave Types';
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query->effective()->forSector();
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return $model instanceof LeaveModel && static::ownsCompanyRow($model);
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return $model instanceof LeaveModel && static::ownsCompanyRow($model);
    }

    public static function canDeleteResource(Model $model): bool
    {
        return $model instanceof LeaveModel && static::ownsCompanyRow($model);
    }

    /**
     * The shared catalogue is read-only; a company only maintains its own rows.
     */
    protected static function ownsCompanyRow(LeaveModel $model): bool
    {
        $companyId = $model->getAttribute('company_id');
        $userCompanyId = Auth::user()?->company_id;

        return $companyId !== null
            && $userCompanyId !== null
            && (int) $companyId === (int) $userCompanyId;
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    /**
     * The name, code and legal source belong to the system row, so they are
     * fixed once a company row exists. Only policy values stay editable.
     */
    public function formFields(Request $request, bool $creating = true): array
    {
        $fields = parent::formFields($request, $creating);

        if ($creating) {
            return $fields;
        }

        foreach ($fields as $field) {
            if (in_array($field->attribute, ['name', 'code', 'description'], true)) {
                $field->readonly();
            }
        }

        return $fields;
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Text::make('Code', 'code')
                ->sortable(),

            Text::make('Description')
                ->hideFromIndex()
                ->sortable(),

            Number::make('Allow per year', 'allow_per_year')
                ->default(0),

            Boolean::make('Deductible', 'deductable'),

            Select::make('Deduct from', 'deduct_to')
                ->options([0 => 'None'] + LeaveModel::query()->effective()->forSector()->orderBy('name')->pluck('name', 'id')->all())
                ->default(0)
                ->hideFromIndex(),

            Boolean::make('Active', 'is_active')->default(true),
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
        return [
            (new OverrideLeaveType($this->resource))->onlyOnDetail(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();
    }
}
