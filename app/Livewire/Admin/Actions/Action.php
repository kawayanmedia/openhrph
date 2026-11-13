<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Field;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class Action
{
    public string $name = '';

    public string $confirmText = '';

    public string $confirmButtonText = 'Run Action';

    public bool $onlyOnIndex = false;

    public bool $onlyOnDetail = false;

    public bool $onlyOnTableRow = false;

    public bool $onlyOnIndexToolbar = false;

    public mixed $model = null;

    public function __construct(mixed $model = null)
    {
        $this->model = $model;
    }

    public function name(): string
    {
        return $this->name ?: Str::headline(class_basename(static::class));
    }

    public function onlyOnIndex(): static
    {
        $this->onlyOnIndex = true;

        return $this;
    }

    public function onlyOnDetail(): static
    {
        $this->onlyOnDetail = true;

        return $this;
    }

    public function onlyOnTableRow(): static
    {
        $this->onlyOnTableRow = true;

        return $this;
    }

    public function onlyOnIndexToolbar(): static
    {
        $this->onlyOnIndexToolbar = true;

        return $this;
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, Field>
     */
    public function fields(Request $request): array
    {
        return [];
    }

    /**
     * Perform the action on the given models.
     */
    abstract public function handle(Request $request, array $fields, Collection $models): mixed;

    /**
     * Build a success response message.
     */
    public static function message(string $message): string
    {
        return $message;
    }
}
