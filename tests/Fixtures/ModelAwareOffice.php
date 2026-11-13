<?php

namespace Tests\Fixtures;

use App\Livewire\Admin\Resources\Office;
use Illuminate\Http\Request;

class ModelAwareOffice extends Office
{
    public function actions(Request $request): array
    {
        return [
            new ModelAwareOfficeAction,
        ];
    }
}
