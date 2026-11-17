<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\SendEmail;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class SignupUser extends Resource
{
    public static string $model = \App\Models\SignupUser::class;

    public static array $with = ['actionEvents'];

    public static int $priority = 1;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $search = ['id', 'name', 'email'];

    public static function label(): string
    {
        return 'Signed Up Users';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Email')
                ->sortable(),

            Text::make('Name', function () {
                return $this->first_name;
            })->sortable(),

            Boolean::make('owner'),

            Text::make('Referer'),

            Text::make('Activities', function () {
                return $this->actionEvents->count();
            })->sortable(),

            DateTime::make('created_at'),
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
        return [
            new SendEmail,
        ];
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToView(?Model $model): ?bool
    {
        return Auth::user()?->email === 'manny.isles@gmail.com';
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return false;
    }
}
