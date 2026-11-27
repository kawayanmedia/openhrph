<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Document;
use App\Models\Employment as EmploymentModel;
use App\Models\EmploymentContract as EmploymentContractModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class EmploymentContract extends Resource
{
    public static string $model = EmploymentContractModel::class;

    public static string $title = 'template_name';

    public static int $priority = 61;

    public static string $group = 'Employment';

    public static ?string $sector = 'Private';

    public static array $with = ['employment', 'document'];

    public static array $search = ['template_name'];

    public function title(): string
    {
        return (string) ($this->resource?->template_name ?? 'Contract #'.$this->resource?->getKey());
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employment')
                ->options(EmploymentModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            Text::make('Template Name', 'template_name')->rules('nullable', 'max:128'),
            Number::make('Version')->min(1)->readonly()->default(1),
            Date::make('Start Date', 'start_date')->rules('required')->sortable(),
            Date::make('End Date', 'end_date'),
            Select::make('Status')
                ->options([
                    EmploymentContractModel::STATUS_DRAFT => EmploymentContractModel::STATUS_DRAFT,
                    EmploymentContractModel::STATUS_FOR_SIGNATURE => EmploymentContractModel::STATUS_FOR_SIGNATURE,
                    EmploymentContractModel::STATUS_SIGNED => EmploymentContractModel::STATUS_SIGNED,
                    EmploymentContractModel::STATUS_ACTIVE => EmploymentContractModel::STATUS_ACTIVE,
                    EmploymentContractModel::STATUS_EXPIRED => EmploymentContractModel::STATUS_EXPIRED,
                    EmploymentContractModel::STATUS_TERMINATED => EmploymentContractModel::STATUS_TERMINATED,
                ])
                ->default(EmploymentContractModel::STATUS_DRAFT),
            DateTime::make('Signed At', 'signed_at'),
            Text::make('Signed By Employee', 'signed_by_employee')->hideFromIndex(),
            Text::make('Signed By Company', 'signed_by_company')->hideFromIndex(),
            BelongsTo::make('Document')
                ->options(Document::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $employment = EmploymentModel::query()->find($model->employment_id);

        $model->forceFill([
            'company_id' => $employment?->company_id ?? $request->user()->company_id,
        ])->save();
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
