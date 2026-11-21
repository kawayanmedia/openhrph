<?php

namespace Tests\Fixtures;

use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class SectorGatedProbeResource extends Resource
{
    public static ?string $sector = 'Government';

    public function fields(Request $request): array
    {
        return [];
    }
}
