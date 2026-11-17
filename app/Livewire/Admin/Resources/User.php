<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Password;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;

class User extends Resource
{
    public static string $model = \App\Models\User::class;

    public static string $title = 'name';

    public static array $search = ['id', 'name', 'email'];

    public static function indexQuery(Builder $query): Builder
    {
        return static::tenantQuery($query);
    }

    public static function detailQuery(Builder $query): Builder
    {
        return static::tenantQuery($query);
    }

    protected static function tenantQuery(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId === null ? $query : $query->forTenant($tenantId);
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Password::make('Password')
                ->creationRules('required', PasswordRule::defaults())
                ->updateRules('nullable', PasswordRule::defaults()),
        ];
    }
}
