<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\PrivateSector\HireApplicant;
use App\Livewire\Admin\Actions\PrivateSector\IssueJobOffer;
use App\Livewire\Admin\Actions\PrivateSector\ScreenApplication;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Application as ApplicationModel;
use App\Models\JobVacancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use KawayanMedia\Government\Models\Applicant;

class Application extends Resource
{
    public static string $model = ApplicationModel::class;

    public static string $title = 'id';

    public static int $priority = 52;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['vacancy', 'applicant'];

    public static array $search = ['id', 'source'];

    public function title(): string
    {
        return 'Application #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Vacancy', 'vacancy')
                ->options(JobVacancy::query()->orderByDesc('id')->limit(200)->pluck('title', 'id')->toArray())
                ->rules('required'),

            BelongsTo::make('Applicant')
                ->options(Applicant::query()->orderBy('name')->limit(500)->pluck('name', 'id')->toArray())
                ->rules('required'),

            Text::make('Source')->rules('nullable', 'max:64'),
            Text::make('Current Stage', 'current_stage')->hideFromIndex(),

            Badge::make('Status')->map([
                ApplicationModel::STATUS_RECEIVED => 'default',
                ApplicationModel::STATUS_SCREENING => 'info',
                ApplicationModel::STATUS_SHORTLISTED => 'info',
                ApplicationModel::STATUS_ASSESSMENT => 'info',
                ApplicationModel::STATUS_INTERVIEW => 'info',
                ApplicationModel::STATUS_RECOMMENDED => 'warning',
                ApplicationModel::STATUS_OFFER => 'warning',
                ApplicationModel::STATUS_HIRED => 'success',
                ApplicationModel::STATUS_REJECTED => 'danger',
                ApplicationModel::STATUS_WITHDRAWN => 'default',
                ApplicationModel::STATUS_OFFER_DECLINED => 'danger',
                ApplicationModel::STATUS_OFFER_EXPIRED => 'danger',
                ApplicationModel::STATUS_TALENT_POOL => 'default',
            ])->sortable(),

            DateTime::make('Submitted At', 'submitted_at')->hideFromIndex(),
            Textarea::make('Disposition Reason', 'disposition_reason')->hideFromIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $vacancy = JobVacancy::query()->find($model->job_vacancy_id);

        $model->forceFill([
            'company_id' => $vacancy?->company_id ?? $request->user()->company_id,
            'status' => $model->status ?: ApplicationModel::STATUS_RECEIVED,
            'submitted_at' => $model->submitted_at ?? now(),
            'consent_at' => $model->consent_at ?? now(),
            'metadata' => array_merge($model->metadata ?? [], ['applicant_id' => $model->applicant_id]),
        ])->save();
    }

    public function actions(Request $request): array
    {
        return [new ScreenApplication, new IssueJobOffer, new HireApplicant];
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
}
