<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BooleanGroup;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Group extends Resource
{
    public static string $model = \App\Models\Group::class;

    public static int $priority = 1;

    public static string $title = 'name';

    public static string $group = 'Settings';

    public static array $search = [];

    public static function uriKey(): string
    {
        return 'user-groups';
    }

    public static function label(): string
    {
        return 'User Groups';
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query->orderBy('id', 'asc');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required', 'max:255'),

            BooleanGroup::make('Menu and Permissions', 'permissions')
                ->options(group_access())
                ->help('Select the areas this group may access.'),
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

    public static function afterCreate(Request $request, Model $model): void
    {
        Cache::forget('nodes_cache');
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        Cache::forget('nodes_cache');
    }

    public static function authorizedToView(?Model $model): ?bool
    {
        return true;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return true;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return false;
    }
}
