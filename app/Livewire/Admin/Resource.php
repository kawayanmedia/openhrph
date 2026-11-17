<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Filters\Filter;
use App\Livewire\Admin\Lenses\Lens;
use App\Livewire\Admin\Metrics\Metric;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Wireable;

abstract class Resource implements Wireable
{
    /**
     * The model the resource corresponds to.
     */
    public static string $model;

    /**
     * The attribute used as the display title for a model instance.
     */
    public static string $title = 'id';

    /**
     * The columns that should be searched.
     */
    public static array $search = ['id'];

    /**
     * The relationships that should be eager loaded on index queries.
     */
    public static array $with = [];

    /**
     * The logical group associated with the resource (sidebar grouping).
     */
    public static string $group = 'Other';

    /**
     * Custom priority level of the resource for sidebar sorting.
     */
    public static int $priority = 9999;

    public function __construct(public ?Model $resource = null) {}

    /**
     * The URI key used to identify the resource in routes.
     */
    public static function uriKey(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    /**
     * The displayable singular label of the resource.
     */
    public static function label(): string
    {
        return Str::title(Str::snake(class_basename(static::class), ' '));
    }

    /**
     * The displayable plural label of the resource.
     */
    public static function pluralLabel(): string
    {
        return Str::plural(static::label());
    }

    /**
     * Get the displayable value representing the resource.
     */
    public function title(): string
    {
        return (string) ($this->resource?->{static::$title} ?? $this->resource?->getKey() ?? '');
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Get a fresh instance of the model.
     */
    public static function newModel(): Model
    {
        $model = static::$model;

        return new $model;
    }

    /**
     * Whether an authorization policy is registered for this resource's model.
     */
    public static function policyExists(): bool
    {
        return Gate::getPolicyFor(static::$model) !== null;
    }

    /**
     * Resource-level switch for the create screen.
     */
    public static function canCreateResource(): bool
    {
        return true;
    }

    /**
     * Resource-level switch for deleting a record.
     */
    public static function canDeleteResource(Model $model): bool
    {
        return true;
    }

    /**
     * Resource-level authorization override, mirroring Nova's authorizedToCreate().
     *
     * Returning null defers to the policy.
     */
    public static function authorizedToCreate(): ?bool
    {
        return null;
    }

    /**
     * Resource-level authorization override, mirroring Nova's authorizedToView().
     */
    public static function authorizedToView(?Model $model): ?bool
    {
        return null;
    }

    /**
     * Resource-level authorization override, mirroring Nova's authorizedToUpdate().
     */
    public static function authorizedToUpdate(Model $model): ?bool
    {
        return null;
    }

    /**
     * Resource-level authorization override, mirroring Nova's authorizedToDelete().
     */
    public static function authorizedToDelete(Model $model): ?bool
    {
        return null;
    }

    /**
     * Whether the policy actually implements the given ability.
     *
     * Nova treats a missing policy method as "allowed", so mirror that behaviour.
     */
    public static function policyDefines(string $ability): bool
    {
        $policy = Gate::getPolicyFor(static::$model);

        return $policy !== null && is_callable([$policy, $ability]);
    }

    /**
     * Resolve a resource-level override for the ability, if one is declared.
     */
    protected static function authorizationOverride(string $ability, mixed $arguments): ?bool
    {
        return match ($ability) {
            'create' => static::authorizedToCreate(),
            'view' => $arguments instanceof Model ? static::authorizedToView($arguments) : null,
            'update' => $arguments instanceof Model ? static::authorizedToUpdate($arguments) : null,
            'delete' => $arguments instanceof Model ? static::authorizedToDelete($arguments) : null,
            default => null,
        };
    }

    /**
     * Authorize an ability, honouring resource overrides before the policy.
     */
    public static function authorize(string $ability, mixed $arguments): void
    {
        $override = static::authorizationOverride($ability, $arguments);

        if ($override === true) {
            return;
        }

        if ($override === false) {
            throw new AuthorizationException;
        }

        if (! static::policyDefines($ability)) {
            return;
        }

        Gate::authorize($ability, $arguments);
    }

    /**
     * Authorize an action against a record.
     *
     * Nova authorizes actions independently of the resource's update ability, so
     * read-only resources keep their actions (e.g. editing a timesheet).
     */
    public static function authorizeAction(Model $model): void
    {
        if (! static::allows('view', $model)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Check an ability, honouring resource overrides, then the policy.
     *
     * Defaults to true when neither the policy nor the resource implements it.
     */
    public static function allows(string $ability, mixed $arguments): bool
    {
        $override = static::authorizationOverride($ability, $arguments);

        if ($override !== null) {
            return $override;
        }

        if (! static::policyDefines($ability)) {
            return true;
        }

        return Gate::allows($ability, $arguments);
    }

    /**
     * Serialize the resource for Livewire's component snapshot.
     */
    public function toLivewire(): array
    {
        return [
            'class' => static::class,
            'id' => $this->resource?->getKey(),
        ];
    }

    /**
     * Restore the resource from Livewire's component snapshot.
     */
    public static function fromLivewire($value): static
    {
        $class = $value['class'] ?? static::class;

        $resource = new $class;

        $id = $value['id'] ?? null;

        if ($id !== null) {
            $query = $class::newModel()->newQuery();

            if (! empty($class::$with)) {
                $query->with($class::$with);
            }

            $resource->resource = $class::detailQuery($query)->find($id);
        }

        return $resource;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    abstract public function fields(Request $request): array;

    /**
     * Get the fields displayed on the index table.
     *
     * @return array<int, Field>
     */
    public function indexFields(Request $request): array
    {
        return array_values(array_filter($this->fields($request), fn (Field $field) => $field->showOnIndex));
    }

    /**
     * Get the fields displayed on the detail page.
     *
     * @return array<int, Field>
     */
    public function detailFields(Request $request): array
    {
        return array_values(array_filter($this->fields($request), fn (Field $field) => $field->showOnDetail));
    }

    /**
     * Get the fields displayed on the create/update form.
     *
     * @return array<int, Field>
     */
    public function formFields(Request $request, bool $creating = true): array
    {
        return array_values(array_filter(
            $this->fields($request),
            fn (Field $field) => ($creating ? $field->showOnCreating : $field->showOnUpdating)
                && ! $field->hasRelationAttribute()
        ));
    }

    /**
     * Get the cards (metrics) available for the resource index.
     *
     * @return array<int, Metric>
     */
    public function cards(Request $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, Filter>
     */
    public function filters(Request $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, Action>
     */
    public function actions(Request $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, Lens>
     */
    public function lenses(Request $request): array
    {
        return [];
    }

    /**
     * Register a callback to be called after the resource is created.
     */
    public static function afterCreate(Request $request, Model $model): void {}

    /**
     * Register a callback to be called after the resource is updated.
     */
    public static function afterUpdate(Request $request, Model $model): void {}

    /**
     * Destination after creating a record. Returning null opens the detail page.
     */
    public static function redirectAfterCreate(Request $request, Model $model): ?string
    {
        return null;
    }

    /**
     * Destination after updating a record. Returning null opens the detail page.
     */
    public static function redirectAfterUpdate(Request $request, Model $model): ?string
    {
        return null;
    }
}
