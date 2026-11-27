<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Document as DocumentModel;
use App\Models\Employee;
use App\Models\EmployeeCertification as EmployeeCertificationModel;
use Illuminate\Http\Request;

class EmployeeCertification extends Resource
{
    public static string $model = EmployeeCertificationModel::class;

    public static string $title = 'title';

    public static int $priority = 90;

    public static string $group = 'Talent';

    public static array $with = ['employee', 'document'];

    public static array $search = ['title', 'issuer', 'certification_number'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required')
                ->sortable(),
            Text::make('Certification Type', 'certification_type')->rules('required', 'max:64'),
            Text::make('Title')->rules('required', 'max:191'),
            Text::make('Issuer')->rules('nullable', 'max:191'),
            Text::make('Certification Number', 'certification_number')->rules('nullable', 'max:128')->hideFromIndex(),
            Date::make('Issued At', 'issued_at')->sortable(),
            Date::make('Expires At', 'expires_at')->sortable(),
            BelongsTo::make('Document')
                ->options(DocumentModel::query()->orderByDesc('id')->limit(300)->pluck('title', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),
            Badge::make('Status')->map([
                EmployeeCertificationModel::STATUS_ACTIVE => 'success',
                EmployeeCertificationModel::STATUS_EXPIRED => 'warning',
                EmployeeCertificationModel::STATUS_REVOKED => 'danger',
            ])->sortable(),
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
