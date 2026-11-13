<?php

namespace App\Livewire\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class Password extends Field
{
    public string $component = 'password';

    public function __construct()
    {
        // Passwords are only shown on forms by default.
        $this->showOnIndex = false;
        $this->showOnDetail = false;
    }

    public function resolveForForm(Model $model): mixed
    {
        // Never pre-fill password inputs with the stored hash.
        return null;
    }

    public function fill(Model $model, mixed $requestValue, string $requestAttribute): void
    {
        if ($this->computed) {
            return;
        }

        if ($this->fillUsing) {
            call_user_func($this->fillUsing, $model, $this->attribute, $requestValue, $requestAttribute);

            return;
        }

        if ($requestValue === null || $requestValue === '') {
            return;
        }

        $model->setAttribute($this->attribute, bcrypt($requestValue));
    }
}
