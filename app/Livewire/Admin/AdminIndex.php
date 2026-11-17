<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Filters\Filter;
use App\Livewire\Admin\Support\FormData;
use App\Livewire\Admin\Support\ResourceRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\Response;

class AdminIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $resourceKey;

    /** @var resource */
    public $resource;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortAttribute = '';

    #[Url]
    public string $sortDirection = 'asc';

    #[Url]
    public int $perPage = 25;

    public array $filterValues = [];

    public array $selected = [];

    public ?string $actionKey = null;

    public ?int $actionTargetId = null;

    public array $actionFields = [];

    public bool $showActionModal = false;

    public bool $confirmingAction = false;

    public ?int $deletingId = null;

    public bool $deletingSelected = false;

    public bool $showDeleteModal = false;

    public function mount(string $resource): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $this->resourceKey = $resource;
        $this->resource = ResourceRegistry::resolve($resource);

        $this->resource::authorize('viewAny', $this->resource::$model);
    }

    public function render()
    {
        return view('livewire.admin.index', [
            'columns' => $this->columns(),
            'rows' => $this->rows(),
            'filters' => $this->filters(),
            'actions' => $this->actions(),
            'toolbarActions' => $this->toolbarActions(),
            'bulkActions' => $this->bulkActions(),
            'activeAction' => $this->activeAction(),
            'actionFieldList' => $this->actionFieldDefinitions(),
            'cards' => $this->resource->cards(request()),
            'canCreate' => $this->resource::canCreateResource()
                && $this->resource::allows('create', $this->resource::$model),
        ])->layout('layouts.admin')->title($this->resource::pluralLabel());
    }

    /** @return array<int, Field> */
    protected function columns(): array
    {
        $columns = $this->resource->indexFields(request());

        $visible = $this->filterValues['fields-filter'] ?? null;

        if (is_array($visible) && $visible !== []) {
            $columns = array_values(array_filter($columns, function (Field $field) use ($visible) {
                return $field->attribute === null || in_array($field->attribute, $visible, true);
            }));
        }

        return $columns;
    }

    /** @return array<int, Filter> */
    protected function filters(): array
    {
        return $this->resource->filters(request());
    }

    /** @return array<int, Action> */
    protected function actions(): array
    {
        return array_values(array_filter($this->resource->actions(request()), function (Action $action) {
            return ! $action->onlyOnIndexToolbar && ! $action->onlyOnDetail;
        }));
    }

    /** @return array<int, Action> */
    protected function toolbarActions(): array
    {
        return array_values(array_filter($this->resource->actions(request()), function (Action $action) {
            return $action->onlyOnIndexToolbar;
        }));
    }

    /** @return array<int, Action> */
    protected function bulkActions(): array
    {
        return array_values(array_filter($this->resource->actions(request()), function (Action $action) {
            return ! $action->onlyOnIndexToolbar
                && ! $action->onlyOnDetail
                && ! $action->onlyOnIndex
                && ! $action->onlyOnTableRow;
        }));
    }

    protected function rows()
    {
        $model = $this->resource::newModel();
        $query = $model->newQuery();

        $query = $this->resource::indexQuery($query);

        if (! empty($this->resource::$with)) {
            $query->with($this->resource::$with);
        }

        $this->applySearch($query);
        $this->applyFilters($query);
        $this->applySort($query);

        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;

        return $query->paginate($perPage);
    }

    protected function applySearch($query): void
    {
        if ($this->search === '') {
            return;
        }

        $columns = $this->resource::$search;

        if (empty($columns)) {
            return;
        }

        $query->where(function ($q) use ($columns) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    $q->orWhereHas($relation, function ($relationQuery) use ($relatedColumn) {
                        $relationQuery->where($relatedColumn, 'like', "%{$this->search}%");
                    });

                    continue;
                }

                $q->orWhere($column, 'like', "%{$this->search}%");
            }
        });
    }

    protected function applyFilters($query): void
    {
        foreach ($this->filters() as $filter) {
            $key = $filter->uriKey();

            if (array_key_exists($key, $this->filterValues) && $this->filterValues[$key] !== '' && $this->filterValues[$key] !== null) {
                $filter->apply(request(), $query, $this->filterValues[$key]);
            }
        }
    }

    protected function applySort($query): void
    {
        $model = $query->getModel();

        if (
            $this->sortAttribute !== ''
            && in_array($this->sortAttribute, $this->sortableAttributes(), true)
            && Schema::hasColumn($model->getTable(), $this->sortAttribute)
        ) {
            $query->orderBy($this->sortAttribute, $this->sortDirection === 'desc' ? 'desc' : 'asc');

            return;
        }

        if ($this->sortAttribute !== '') {
            // Ignore columns that are not sortable or do not exist on the table.
            $this->sortAttribute = '';
        }

        if ($model->usesTimestamps() && Schema::hasColumn($model->getTable(), $model->getCreatedAtColumn())) {
            $query->latest();
        } else {
            $query->orderByDesc($model->getKeyName());
        }
    }

    /**
     * @return array<int, string>
     */
    protected function sortableAttributes(): array
    {
        return collect($this->resource->indexFields(request()))
            ->filter(fn (Field $field) => $field->sortable && $field->attribute !== null)
            ->pluck('attribute')
            ->unique()
            ->values()
            ->all();
    }

    public function sortBy(string $attribute): void
    {
        if (! in_array($attribute, $this->sortableAttributes(), true)) {
            return;
        }

        if ($this->sortAttribute === $attribute) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortAttribute = $attribute;
        $this->sortDirection = 'asc';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterValues(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->filterValues = [];
        $this->search = '';
        $this->resetPage();
    }

    public function openAction(string $key, ?int $id = null): void
    {
        $action = $this->resolveAction($key);

        abort_unless($action, 404);

        if ($id !== null) {
            $model = $this->resource::newModel()->newQuery()->findOrFail($id);

            $this->resource::authorizeAction($model);

            $action->model = $model;
        }

        $this->actionKey = $key;
        $this->actionTargetId = $id;
        $this->actionFields = $this->initialActionFields($action);
        $this->confirmingAction = false;
        $this->showActionModal = true;
    }

    public function runAction(): mixed
    {
        $action = $this->resolveAction((string) $this->actionKey);

        abort_unless($action, 404);

        $models = $this->actionTargets();

        foreach ($models as $model) {
            $this->resource::authorizeAction($model);
        }

        // Action field definitions may depend on the record being acted on.
        $action->model = $models->first() ?? $action->model;

        $fields = $this->actionFieldDefinitions($action);
        $this->validateActionFields($fields);

        $result = $action->handle(request(), $this->actionFields, $models);

        $this->selected = [];
        $this->showActionModal = false;
        $this->actionKey = null;
        $this->actionTargetId = null;
        $this->actionFields = [];

        if ($result instanceof Response) {
            return $result;
        }

        session()->flash('admin.action', is_string($result) && $result !== ''
            ? $result
            : $action::message('Action completed.'));

        return null;
    }

    public function closeAction(): void
    {
        $this->showActionModal = false;
        $this->actionKey = null;
        $this->actionTargetId = null;
        $this->actionFields = [];
        $this->confirmingAction = false;
        $this->resetActionErrors();
    }

    protected function resolveAction(string $key): ?Action
    {
        foreach ($this->resource->actions(request()) as $action) {
            if (class_basename($action) === $key || Str::kebab(class_basename($action)) === $key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * The action used by the currently open modal.
     */
    protected function activeAction(): ?Action
    {
        if (! $this->actionKey) {
            return null;
        }

        $action = $this->resolveAction($this->actionKey);

        if ($action === null) {
            return null;
        }

        if ($this->actionTargetId !== null) {
            $action->model = $this->resource::newModel()->newQuery()->find($this->actionTargetId);
        }

        return $action;
    }

    /**
     * @return array<int, Field>
     */
    protected function actionFieldDefinitions(?Action $action = null): array
    {
        $action ??= $this->activeAction();

        if ($action === null) {
            return [];
        }

        $fields = $action->fields(request());

        $formData = new FormData($this->actionFields);

        foreach ($fields as $field) {
            $field->applyDependencies($formData, request());
        }

        $this->actionFields = $formData->toArray();

        $sources = [];

        foreach ($fields as $field) {
            foreach ($field->dependsOnFields as $dependency) {
                $sources[$dependency] = true;
            }
        }

        foreach ($fields as $field) {
            $field->live = $field->attribute !== null && isset($sources[$field->attribute]);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    protected function initialActionFields(Action $action): array
    {
        $values = [];

        foreach ($action->fields(request()) as $field) {
            if ($field->attribute === null) {
                continue;
            }

            $default = $field->default;

            $values[$field->attribute] = $default instanceof \Closure ? $default() : $default;
        }

        return $values;
    }

    /**
     * @param  array<int, Field>  $fields
     */
    protected function validateActionFields(array $fields): void
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            if (! $field->isFormInput()) {
                continue;
            }

            $fieldRules = $field->validationRules(true);

            if (empty($fieldRules)) {
                continue;
            }

            $key = 'actionFields.'.$field->attribute;
            $rules[$key] = $fieldRules;
            $attributes[$key] = $field->name ?: (string) $field->attribute;
        }

        if (empty($rules)) {
            $this->resetActionErrors();

            return;
        }

        $this->validate($rules, [], $attributes);
    }

    protected function resetActionErrors(): void
    {
        $errors = array_keys($this->getErrorBag()->toArray());

        foreach ($errors as $key) {
            if (str_starts_with($key, 'actionFields.')) {
                $this->resetErrorBag($key);
            }
        }
    }

    protected function actionTargets(): Collection
    {
        $class = $this->resource::$model;

        if ($this->actionTargetId !== null) {
            return new Collection([$class::find($this->actionTargetId)]);
        }

        if (! empty($this->selected)) {
            return new Collection($class::whereIn('id', $this->selected)->get());
        }

        return new Collection;
    }

    /**
     * Whether the current selection may be deleted.
     */
    public function canDeleteSelection(): bool
    {
        if (empty($this->selected)) {
            return false;
        }

        $class = $this->resource::$model;
        $model = $class::find($this->selected[0]);

        return $model !== null
            && $this->resource::canDeleteResource($model)
            && $this->resource::allows('delete', $model);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->deletingSelected = false;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteSelected(): void
    {
        $this->deletingId = null;
        $this->deletingSelected = true;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingSelected = false;
    }

    public function delete(): void
    {
        $class = $this->resource::$model;
        $count = 0;

        if ($this->deletingId !== null) {
            $models = new Collection([$class::findOrFail($this->deletingId)]);
        } elseif ($this->deletingSelected) {
            $models = new Collection($class::whereIn('id', $this->selected)->get());
        } else {
            $models = new Collection;
        }

        DB::transaction(function () use ($models, &$count) {
            foreach ($models as $model) {
                abort_unless($this->resource::canDeleteResource($model), 403);

                $this->resource::authorize('delete', $model);

                $model->delete();
                $count++;
            }
        });

        $this->selected = [];
        $this->cancelDelete();

        session()->flash('admin.action', $count === 1 ? 'Record deleted.' : "{$count} records deleted.");
    }
}
