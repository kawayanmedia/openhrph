<?php

namespace Tests\Fixtures;

use App\Livewire\Admin\Resources\Office;
use Illuminate\Database\Eloquent\Model;

class ReadOnlyOffice extends Office
{
    public static function authorizedToCreate(): ?bool
    {
        return false;
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
