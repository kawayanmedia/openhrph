<?php

use App\Jurisdictions\Philippines\Government\Admin\Resources\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Admin\Resources\SalaryGrade;
use App\Livewire\Admin\Support\Navigation;
use App\Livewire\Admin\Support\ResourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

it('exposes government resources and menu entries to a government installation', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    expect(ResourceRegistry::resolve('plantilla-item'))->toBeInstanceOf(PlantillaItem::class)
        ->and(ResourceRegistry::resolve('salary-grade'))->toBeInstanceOf(SalaryGrade::class);

    $labels = Navigation::sections()
        ->flatMap(fn (array $section) => collect($section['items'])->pluck('label'))
        ->all();

    expect($labels)->toContain('Plantilla Items')
        ->and($labels)->toContain('Salary Grades');
});

it('hides government resources and menu entries from a private installation', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    expect(fn () => ResourceRegistry::resolve('plantilla-item'))
        ->toThrow(NotFoundHttpException::class);

    $labels = Navigation::sections()
        ->flatMap(fn (array $section) => collect($section['items'])->pluck('label'))
        ->all();

    expect($labels)->not->toContain('Plantilla Items')
        ->and($labels)->not->toContain('Salary Grades');
});
