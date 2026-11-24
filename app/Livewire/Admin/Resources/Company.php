<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Company as CompanyModel;
use App\Services\Company\CompanyProvisioner;
use App\Support\Installation\Installation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class Company extends Resource
{
    public static string $model = CompanyModel::class;

    public static int $priority = 1;

    public static string $title = 'name';

    public static string $group = 'Admin';

    public static array $search = ['id', 'name', 'code', 'trade_name', 'tin'];

    public static array $with = ['employees'];

    public static function label(): string
    {
        return 'Company';
    }

    public static function pluralLabel(): string
    {
        return 'Companies';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    /**
     * Company management is not tenant-scoped: list only the companies the
     * signed-in user belongs to unless they are a platform administrator.
     */
    public static function indexQuery(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user === null || $user->isPlatformAdmin()) {
            return $query;
        }

        return $query->whereIn('id', $user->tenantIds() ?: [0]);
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->rules('required', 'max:64')
                ->sortable(),

            Text::make('Code', 'code')
                ->rules('nullable', 'max:32', Rule::unique('companies', 'code')->ignore($this->resource?->getKey()))
                ->sortable(),

            Text::make('Trade Name', 'trade_name')
                ->rules('nullable', 'max:64'),

            Text::make('Employees', function () {
                return $this->employees->count();
            })->sortable()->onlyOnIndex(),

            Installation::sector() !== null
                ? Text::make('Type', fn () => Installation::sector())
                    ->help('Locked to the installation sector by the installer.')
                : Select::make('Type')
                    ->options([
                        'Private' => 'Private',
                        'Government' => 'Government',
                        'Non-Profit' => 'Non-Profit',
                    ])
                    ->help('Government agencies remit GSIS instead of SSS on payroll.')
                    ->sortable()
                    ->rules('required'),

            Text::make('TIN', 'tin')
                ->placeholder('000-000-000-000')
                ->rules('required'),

            Text::make('RDO', 'rdo')
                ->placeholder('049')
                ->rules('required'),

            Text::make('SSS', 'sss')
                ->placeholder('00-0000000-0')
                ->help('Leave blank for a Government installation that remits GSIS.'),

            Text::make('HDMF', 'hdmf')
                ->placeholder('0000-0000-0000')
                ->rules('required'),

            Text::make('PhilHealth', 'phil_health')
                ->placeholder('00-000000000-0')
                ->rules('required'),

            Text::make('Email', 'email')
                ->withMeta(['extraAttributes' => ['type' => 'email']]),

            Text::make('Telephone', 'tel_number'),

            Text::make('Website', 'website'),

            Text::make('Address No.', 'address_no')
                ->hideFromIndex(),

            Text::make('Street', 'street')
                ->hideFromIndex(),

            Text::make('ZIP Code', 'zip_code')
                ->withMeta(['extraAttributes' => ['type' => 'number']])
                ->hideFromIndex(),

            Date::make('Date Registered', 'date_registered')
                ->hideFromIndex(),

            Select::make('Formation')
                ->options([
                    'Sole Proprietorship' => 'Sole Proprietorship',
                    'Partnership' => 'Partnership',
                    'Corporation' => 'Corporation',
                ])
                ->hideFromIndex(),

            Text::make('Line of Business', 'line_business')
                ->hideFromIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        app(CompanyProvisioner::class)->provision($model, $user);
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
