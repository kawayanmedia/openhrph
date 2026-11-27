<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Files;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Company;
use App\Models\Document as DocumentModel;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Document extends Resource
{
    public static string $model = DocumentModel::class;

    public static int $priority = 40;

    public static string $group = 'HR';

    public static array $with = ['versions', 'uploader'];

    public static array $search = ['id', 'title', 'document_number'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Title')->rules('required', 'max:255')->sortable(),

            Select::make('Category')
                ->options(array_combine(DocumentModel::CATEGORIES, DocumentModel::CATEGORIES))
                ->default(DocumentModel::CATEGORY_OTHER)
                ->sortable(),

            Select::make('Classification')
                ->options(array_combine(DocumentModel::CLASSIFICATIONS, DocumentModel::CLASSIFICATIONS))
                ->default(DocumentModel::CLASSIFICATION_INTERNAL)
                ->sortable(),

            Text::make('Document Number', 'document_number')->rules('nullable', 'max:64'),

            Select::make('Owner Type', 'owner_type')
                ->options([
                    Employee::class => 'Employee',
                    Company::class => 'Company',
                ])
                ->nullable()
                ->hideFromIndex(),

            Number::make('Owner ID', 'owner_id')
                ->nullable()
                ->hideFromIndex(),

            Textarea::make('Description')->hideFromIndex(),

            Date::make('Issued At', 'issued_at'),
            Date::make('Expires At', 'expires_at')->sortable(),
            Date::make('Retention Until', 'retention_until')->hideFromIndex(),

            Select::make('Status')
                ->options([
                    DocumentModel::STATUS_ACTIVE => DocumentModel::STATUS_ACTIVE,
                    DocumentModel::STATUS_SUPERSEDED => DocumentModel::STATUS_SUPERSEDED,
                    DocumentModel::STATUS_EXPIRED => DocumentModel::STATUS_EXPIRED,
                    DocumentModel::STATUS_ARCHIVED => DocumentModel::STATUS_ARCHIVED,
                ])
                ->default(DocumentModel::STATUS_ACTIVE),

            Files::make('File', 'file'),

            Text::make('Download', function () {
                if (! $this->getFirstMedia('file')) {
                    return null;
                }

                $url = route('admin.documents.download', $this->getKey());

                return '<a class="ui-btn-secondary inline-flex" href="'.e($url).'">Download current file</a>';
            })->asHtml()->onlyOnDetail(),

            Text::make('Versions', function () {
                return $this->versions
                    ->map(fn ($version) => 'v'.$version->version.' · '.e($version->file_name).' · '.$version->created_at?->toDateTimeString())
                    ->implode('<br>');
            })->asHtml()->onlyOnDetail(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->forceFill([
            'uploaded_by' => Auth::id(),
            'status' => $model->status ?: DocumentModel::STATUS_ACTIVE,
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
