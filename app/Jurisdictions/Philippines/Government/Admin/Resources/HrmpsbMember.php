<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\HrmpsbCommittee as HrmpsbCommitteeModel;
use App\Jurisdictions\Philippines\Government\Models\HrmpsbMember as HrmpsbMemberModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Http\Request;

class HrmpsbMember extends Resource
{
    public static string $model = HrmpsbMemberModel::class;

    public static string $title = 'role';

    public static int $priority = 28;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['committee', 'employee'];

    public static array $search = ['external_name', 'role'];

    public function title(): string
    {
        return (string) ($this->resource?->employee?->fullname ?? $this->resource?->external_name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Committee')
                ->options(HrmpsbCommitteeModel::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required'),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->nullable(),
            Text::make('External Name', 'external_name')->rules('nullable', 'max:128'),
            Select::make('Role')
                ->options([
                    HrmpsbMemberModel::ROLE_CHAIR => HrmpsbMemberModel::ROLE_CHAIR,
                    HrmpsbMemberModel::ROLE_MEMBER => HrmpsbMemberModel::ROLE_MEMBER,
                    HrmpsbMemberModel::ROLE_SECRETARIAT => HrmpsbMemberModel::ROLE_SECRETARIAT,
                ])
                ->default(HrmpsbMemberModel::ROLE_MEMBER),
            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),
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
