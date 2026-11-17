<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Fields\HasMany;
use App\Livewire\Admin\Support\FormData;
use App\Livewire\Admin\Support\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminDetail extends Component
{
    use WithFileUploads;

    public string $resourceKey;

    /** @var resource */
    public $resource;

    public Model $model;

    public ?string $actionKey = null;

    public array $actionFields = [];

    public bool $showActionModal = false;

    public bool $showDeleteModal = false;

    public function mount(string $resource, mixed $resourceId): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $this->resourceKey = $resource;
        $this->resource = ResourceRegistry::resolve($resource);

        $this->model = $this->findModel($resourceId);

        $this->resource::authorize('view', $this->model);
    }

    protected function findModel(mixed $resourceId): Model
    {
        $model = $this->resource::newModel();
        $query = $model->newQuery();

        if (! empty($this->resource::$with)) {
            $query->with($this->resource::$with);
        }

        $model = $this->resource::detailQuery($query)->findOrFail($resourceId);

        $this->resource->resource = $model;

        return $model;
    }

    public function render()
    {
        return view('livewire.admin.detail', [
            'fields' => $this->resource->detailFields(request()),
            'actions' => $this->detailActions(),
            'relationFields' => $this->relationFields(),
            'activeAction' => $this->activeAction(),
            'actionFieldList' => $this->actionFieldDefinitions(),
            'canUpdate' => $this->resource::allows('update', $this->model),
            'canDelete' => $this->resource::canDeleteResource($this->model)
                && $this->resource::allows('delete', $this->model),
        ])->layout('layouts.admin')->title($this->resource->title());
    }

    public function openDetailAction(string $key): void
    {
        $action = $this->resolveAction($key);

        abort_unless($action, 404);

        $action->model = $this->model;

        $this->actionKey = $key;
        $this->actionFields = $this->initialActionFields($action);
        $this->showActionModal = true;
    }

    public function runDetailAction(): mixed
    {
        $action = $this->resolveAction((string) $this->actionKey);

        abort_unless($action, 404);

        $this->resource::authorizeAction($this->model);

        $action->model = $this->model;

        $fields = $this->actionFieldDefinitions($action);
        $this->validateActionFields($fields);

        $result = $action->handle(request(), $this->actionFields, new Collection([$this->model]));

        $this->closeAction();

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
        $this->actionFields = [];

        foreach (array_keys($this->getErrorBag()->toArray()) as $key) {
            if (str_starts_with($key, 'actionFields.')) {
                $this->resetErrorBag($key);
            }
        }
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

    protected function activeAction(): ?Action
    {
        if (! $this->actionKey) {
            return null;
        }

        $action = $this->resolveAction($this->actionKey);

        if ($action !== null) {
            $action->model = $this->model;
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

        foreach ($fields as $field) {
            if (! $field->isFormInput()) {
                continue;
            }

            $fieldRules = $field->validationRules(true);

            if (! empty($fieldRules)) {
                $rules['actionFields.'.$field->attribute] = $fieldRules;
            }
        }

        if (! empty($rules)) {
            $this->validate($rules);
        }
    }

    protected function detailActions(): array
    {
        return array_values(array_filter($this->resource->actions(request()), function (Action $action) {
            return ! $action->onlyOnIndex && ! $action->onlyOnIndexToolbar && ! $action->onlyOnTableRow;
        }));
    }

    protected function relationFields(): array
    {
        return array_values(array_filter(
            $this->resource->fields(request()),
            fn (Field $field) => $field instanceof HasMany
        ));
    }

    public function confirmDelete(): void
    {
        $this->resource::authorize('delete', $this->model);

        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        abort_unless($this->resource::canDeleteResource($this->model), 403);

        $this->resource::authorize('delete', $this->model);

        $this->model->delete();

        session()->flash('admin.action', 'Record deleted.');

        $this->redirectRoute('admin.resource.index', ['resource' => $this->resourceKey]);
    }
}
