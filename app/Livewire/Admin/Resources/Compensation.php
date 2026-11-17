<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\OverrideCompensation;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Compensation as CompensationModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Compensation extends Resource
{
    public static string $model = CompensationModel::class;

    public static int $priority = 4;

    public static string $title = 'id';

    public static string $group = 'Payroll';

    public static array $search = ['code', 'name', 'tax_class'];

    public static function label(): string
    {
        return 'Compensations';
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query->effective()->forSector();
    }

    public static function canCreateResource(): bool
    {
        return false;
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return $model instanceof CompensationModel && static::ownsOverride($model);
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return $model instanceof CompensationModel && static::ownsOverride($model);
    }

    public static function canDeleteResource(Model $model): bool
    {
        return $model instanceof CompensationModel && static::ownsOverride($model);
    }

    /**
     * A company may only change the amount and ceiling of its own override.
     */
    protected static function ownsOverride(CompensationModel $model): bool
    {
        $companyId = $model->getAttribute('company_id');
        $userCompanyId = auth()->user()?->company_id;

        return $companyId !== null
            && $userCompanyId !== null
            && (int) $companyId === (int) $userCompanyId;
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code', 'code')
                ->sortable()
                ->readonly()
                ->rules('required'),

            Text::make('Name', 'name')
                ->sortable()
                ->readonly()
                ->rules('required'),

            Select::make('Group', 'group')
                ->options(CompensationModel::groupOptions())
                ->sortable()
                ->readonly()
                ->rules('required')
                ->default(CompensationModel::GROUP_COMPENSATION),

            Select::make('Tax Class', 'tax_class_code')
                ->options(CompensationModel::TAX_CLASS_OPTIONS)
                ->sortable()
                ->readonly()
                ->rules('required')
                ->help('BIR tax class. It decides which payroll detail column the amount is reported in.'),

            Boolean::make('Taxable', 'is_taxable')
                ->readonly()
                ->default(true),

            Select::make('Amount Basis', 'amount_basis')
                ->options([
                    'Fixed' => 'Fixed',
                    'Salary Based' => 'Salary Based',
                ])
                ->readonly()
                ->hideFromIndex()
                ->onlyOnForms()
                ->dependsOn(['group'], function (Select $field, Request $request, FormData $formData) {
                    $isBonusOrCommission = in_array($formData->group, [
                        CompensationModel::GROUP_BONUS,
                        CompensationModel::GROUP_COMMISSION,
                    ], true);

                    if ($isBonusOrCommission) {
                        $field->show()->rules('required');

                        return;
                    }

                    $field->hide()->rules();
                }),

            Select::make('Frequency', 'frequency')
                ->options([
                    'One-Time' => 'One-Time',
                    'Periodic' => 'Periodic',
                ])
                ->readonly()
                ->hideFromIndex()
                ->onlyOnForms()
                ->dependsOn(['group'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->group === CompensationModel::GROUP_BONUS) {
                        $field->show()->rules('required');

                        return;
                    }

                    $field->hide()->rules();
                }),

            Currency::make('Maximum non-taxable amount', 'maximum_non_tax')
                ->hideFromIndex()
                ->onlyOnForms()
                ->currency(currency_symbol())
                ->help('Per-annum ceiling. A de minimis allowance is capped by the statutory de minimis table, prorated to the pay period.')
                ->dependsOn(['group', 'is_taxable'], function (Currency $field, Request $request, FormData $formData) {
                    $isBonusOrCommission = in_array($formData->group, [
                        CompensationModel::GROUP_BONUS,
                        CompensationModel::GROUP_COMMISSION,
                    ], true);

                    $isNonTaxableAllowance = $formData->group === CompensationModel::GROUP_ALLOWANCE
                        && in_array($formData->is_taxable, [false, 0, '0'], true);

                    if (! $isBonusOrCommission && ! $isNonTaxableAllowance) {
                        $field->hide()->rules();

                        return;
                    }

                    $field->show();

                    $isBonusOrCommission && in_array($formData->is_taxable, [false, 0, '0'], true)
                        ? $field->rules('required')
                        : $field->rules();
                }),

            Select::make('Tax Option', 'tax_option')
                ->options([
                    'fully_taxable' => 'Fully Taxable',
                    'partially_nontaxable' => 'Partially Non-Taxable',
                    'fully_nontaxable' => 'Fully Non-Taxable',
                ])
                ->sortable()
                ->readonly()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Currency::make('Amount', 'amount')
                ->rules('required')
                ->sortable()
                ->currency(currency_symbol())
                ->readonly()
                ->onlyOnForms()
                ->help('Partially Non Taxable Ceiling Amount (Per Annum)')
                ->dependsOn(['is_taxable', 'tax_option'], function (Currency $field, Request $request, FormData $formData) {
                    $field->hide();

                    if ($formData->tax_option === 'partially_nontaxable') {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->show();
                    }
                }),
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
            (new OverrideCompensation($this->resource))->onlyOnDetail(),
        ];
    }
}
