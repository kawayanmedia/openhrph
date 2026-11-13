<?php

use App\Console\Concerns\RunsForTenants;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('iterates every company inside its own tenant context', function () {
    $one = Company::factory()->create(['name' => 'One']);
    $two = Company::factory()->create(['name' => 'Two']);

    $runner = new class
    {
        use RunsForTenants;

        /** @var array<int, int> */
        public array $seen = [];

        public function runAll(): void
        {
            $this->eachTenant(function ($tenant): void {
                $this->seen[] = $tenant->getKey();
            });
        }
    };

    $runner->runAll();

    expect($runner->seen)->toBe([$one->id, $two->id]);
});

it('runs for a single company when requested', function () {
    $one = Company::factory()->create(['name' => 'One']);
    Company::factory()->create(['name' => 'Two']);

    $runner = new class
    {
        use RunsForTenants;

        /** @var array<int, int> */
        public array $seen = [];

        public function runOne(int $companyId): void
        {
            $this->runForTenant($companyId, function ($tenant): void {
                $this->seen[] = $tenant->getKey();
            });
        }
    };

    $runner->runOne($one->id);

    expect($runner->seen)->toBe([$one->id]);
});
