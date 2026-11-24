<?php

namespace App\Livewire\Admin;

use App\Services\Search\GlobalSearchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    /**
     * @var array<string, array<int, array{label: string, description: string, url: string}>>
     */
    public array $groups = [];

    public function updatedQuery(): void
    {
        $this->groups = $this->results();
    }

    public function clear(): void
    {
        $this->query = '';
        $this->groups = [];
    }

    public function render()
    {
        return view('livewire.admin.global-search');
    }

    /**
     * @return array<string, array<int, array{label: string, description: string, url: string}>>
     */
    private function results(): array
    {
        $user = Auth::user();

        if ($user === null || mb_strlen(trim($this->query)) < 2) {
            return [];
        }

        return app(GlobalSearchService::class)->search($user, $this->query)->all();
    }
}
