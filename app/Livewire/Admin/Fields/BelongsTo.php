<?php

namespace App\Livewire\Admin\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BelongsTo extends Field
{
    public string $component = 'belongs-to';

    public ?string $resource = null;

    public ?string $relation = null;

    public ?string $titleKey = null;

    public ?string $foreignKey = null;

    public array $options = [];

    public bool $searchable = true;

    public bool $showCreateRelationButton = false;

    public function __construct()
    {
        // BelongsTo fields are hidden from the index by default.
        $this->showOnIndex = false;
    }

    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    public function relation(string $relation): static
    {
        $this->relation = $relation;

        return $this;
    }

    public function showCreateRelationButton(): static
    {
        $this->showCreateRelationButton = true;

        return $this;
    }

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function titleKey(string $titleKey): static
    {
        $this->titleKey = $titleKey;

        return $this;
    }

    public function withoutTrashed(): static
    {
        return $this;
    }

    /**
     * The Eloquent relationship name.
     */
    public function relationName(): string
    {
        return $this->relation ?? (string) $this->attribute;
    }

    /**
     * Resolve the foreign key column from the model relationship.
     */
    public function foreignKeyName(Model $model): string
    {
        if ($this->foreignKey !== null) {
            return $this->foreignKey;
        }

        $relation = $this->relationName();

        if (method_exists($model, $relation)) {
            try {
                $instance = $model->{$relation}();

                if ($instance instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                    return $instance->getForeignKeyName();
                }
            } catch (\Throwable) {
                // Fall through to the naming convention below.
            }
        }

        return Str::snake($relation).'_id';
    }

    public function resolveForDisplay(Model $model, ?string $attribute = null): mixed
    {
        $relation = $this->relationName();

        if (method_exists($model, $relation)) {
            $related = $model->{$relation};

            if ($related) {
                $candidates = $this->titleKey !== null
                    ? [$this->titleKey]
                    : ['name', 'title', 'fullname', 'full_name'];

                foreach ($candidates as $candidate) {
                    $value = $related->getAttribute($candidate);

                    if ($value !== null && $value !== '') {
                        return $value;
                    }
                }

                return $related->getKey();
            }
        }

        return $model->getAttribute($this->foreignKeyName($model));
    }

    public function resolveForForm(Model $model): mixed
    {
        return $model->getAttribute($this->foreignKeyName($model));
    }

    public function fill(Model $model, mixed $requestValue, string $requestAttribute): void
    {
        $model->setAttribute($this->foreignKeyName($model), $requestValue !== '' ? $requestValue : null);
    }

    public function validationRules(bool $creating, mixed $resourceId = null): array
    {
        $rules = parent::validationRules($creating, $resourceId);

        if ($this->resource !== null && ! empty($rules)) {
            $table = (new $this->resource)::newModel()->getTable();
            $rules[] = 'exists:'.$table.',id';
        }

        return $rules;
    }
}
