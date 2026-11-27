<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\QualificationScreening as QualificationScreeningModel;
use App\Jurisdictions\Philippines\Government\Models\QualificationStandard as QualificationStandardModel;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class QualificationScreening extends Resource
{
    public static string $model = QualificationScreeningModel::class;

    public static string $title = 'id';

    public static int $priority = 30;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['employee', 'standard', 'screener'];

    public static array $search = ['result'];

    public function title(): string
    {
        return 'Screening #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList()),
            BelongsTo::make('Standard', 'standard')
                ->options(QualificationStandardModel::query()->orderBy('position_title')->pluck('position_title', 'id')->toArray()),
            Text::make('Standard Version', 'standard_version')->hideFromIndex(),
            Badge::make('Result')->map([
                QualificationScreeningModel::RESULT_QUALIFIED => 'success',
                QualificationScreeningModel::RESULT_DISQUALIFIED => 'danger',
            ])->sortable(),
            DateTime::make('Screened At', 'screened_at')->sortable(),
            Text::make('Deficiencies', 'deficiencies')
                ->displayUsing(fn ($value) => $this->formatList($value))
                ->asHtml()
                ->onlyOnDetail(),
            Text::make('Evidence', 'evidence')
                ->displayUsing(fn ($value) => $this->formatEvidence($value))
                ->asHtml()
                ->onlyOnDetail(),
        ];
    }

    protected function formatList(?array $items): string
    {
        if ($items === null || $items === []) {
            return '—';
        }

        return '<ul class="list-disc pl-4 text-sm">'
            .collect($items)->map(fn ($item) => '<li>'.e((string) $item).'</li>')->implode('')
            .'</ul>';
    }

    protected function formatEvidence(?array $evidence): string
    {
        if ($evidence === null || $evidence === []) {
            return '—';
        }

        return '<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs dark:bg-navy-900">'
            .e(json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            .'</pre>';
    }

    public static function canCreateResource(): bool
    {
        return false;
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(?Model $model = null): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(?Model $model = null): ?bool
    {
        return false;
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
