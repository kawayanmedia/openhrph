<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Password;
use App\Livewire\Admin\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ChangePassword extends Resource
{
    public static string $model = User::class;

    public static int $priority = 5;

    public static string $title = 'name';

    public static string $group = 'Settings';

    public static array $search = [];

    public static function label(): string
    {
        return 'Password';
    }

    public static function canCreateResource(): bool
    {
        return false;
    }

    public static function canDeleteResource(Model $model): bool
    {
        return false;
    }

    public static function redirectAfterUpdate(Request $request, Model $model): ?string
    {
        return url(config('admin.path').'/dashboard');
    }

    public function fields(Request $request): array
    {
        return [
            Password::make('Current Password', 'old_password')
                ->rules('required', 'current_password')
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                }),

            Password::make('New Password', 'password')
                ->rules('required', 'confirmed')
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                }),

            Password::make('Confirm Password', 'password_confirmation')
                ->rules('required')
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                }),
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

    public static function afterUpdate(Request $request, Model $model): void
    {
        $user = $request->user();
        $user->password = bcrypt($request->password);
        $user->save();
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return true;
    }
}
