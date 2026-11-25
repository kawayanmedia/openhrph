<?php

use App\Jurisdictions\Philippines\Government\Admin\Resources\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Admin\Resources\SalaryGrade;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem as PlantillaItemModel;
use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\Support\Navigation;
use App\Livewire\Admin\Support\ResourceRegistry;
use App\Models\Company;
use App\Models\Office;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

it('creates a plantilla item through the government screen', function () {
    $company = Company::factory()->create();

    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    app(TenantContext::class)->set($company->id);

    $office = Office::factory()->create(['company_id' => $company->id]);

    $this->actingAs(User::factory()->create([
        'company_id' => $company->id,
        'owner' => true,
    ]));

    Livewire::test(AdminForm::class, ['resource' => 'plantilla-item'])
        ->set('form.office', $office->id)
        ->set('form.item_number', 'HQ-001')
        ->set('form.position_title', 'Administrative Officer I')
        ->set('form.salary_grade', 11)
        ->set('form.step', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect(PlantillaItemModel::query()->where('item_number', 'HQ-001')->exists())->toBeTrue();
});
