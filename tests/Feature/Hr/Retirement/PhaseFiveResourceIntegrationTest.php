<?php

use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Reports\WorkforceAnalytics;
use App\Livewire\Admin\Resources\ComplianceRule as ComplianceRuleResource;
use App\Livewire\Admin\Resources\ComplianceRuleVersion as ComplianceRuleVersionResource;
use App\Livewire\Admin\Resources\ComplianceSource as ComplianceSourceResource;
use App\Livewire\Admin\Resources\ExternalClaimTracking as ExternalClaimTrackingResource;
use App\Livewire\Admin\Resources\RetirementCalculation as RetirementCalculationResource;
use App\Livewire\Admin\Resources\RetirementCase as RetirementCaseResource;
use App\Livewire\Admin\Resources\RetirementPlan as RetirementPlanResource;
use App\Livewire\Admin\Support\ResourceRegistry;
use App\Models\Company;
use App\Models\RetirementPlan;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Phase Five Co']);

    $this->admin = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'Phase Five Admin',
    ]);

    $this->actingAs($this->admin);

    app(TenantContext::class)->set($this->company->id);

    $this->resources = [
        'retirement-case' => RetirementCaseResource::class,
        'retirement-plan' => RetirementPlanResource::class,
        'retirement-calculation' => RetirementCalculationResource::class,
        'external-claim-tracking' => ExternalClaimTrackingResource::class,
        'compliance-source' => ComplianceSourceResource::class,
        'compliance-rule' => ComplianceRuleResource::class,
        'compliance-rule-version' => ComplianceRuleVersionResource::class,
    ];
});

it('registers every retirement and compliance resource with fields', function (): void {
    app(TenantContext::class)->run($this->company->id, function (): void {
        foreach ($this->resources as $uriKey => $class) {
            expect(ResourceRegistry::find($uriKey))->toBe($class);

            $fields = (new $class)->fields(request());

            expect($fields)->toBeArray()->not->toBeEmpty();
        }
    });
});

it('renders the retirement and compliance administration screens', function (): void {
    $creatable = ['retirement-plan', 'compliance-source'];

    app(TenantContext::class)->run($this->company->id, function () use ($creatable): void {
        foreach (array_keys($this->resources) as $uriKey) {
            Livewire::test(AdminIndex::class, ['resource' => $uriKey])->assertOk();
        }

        foreach ($creatable as $uriKey) {
            Livewire::test(AdminForm::class, ['resource' => $uriKey])->assertOk();
        }
    });
});

it('creates a retirement plan through the generic admin form', function (): void {
    app(TenantContext::class)->run($this->company->id, function (): void {
        Livewire::test(AdminForm::class, ['resource' => 'retirement-plan'])
            ->set('form.name', 'Executive Retirement Plan')
            ->set('form.sector', 'Private')
            ->set('form.plan_type', RetirementPlan::TYPE_DEFINED_CONTRIBUTION)
            ->set('form.eligibility_age', 55)
            ->set('form.minimum_service_years', 10)
            ->set('form.benefit_formula', '{"type":"accumulated_contributions"}')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        expect(RetirementPlan::withoutTenant()->where('name', 'Executive Retirement Plan')->exists())->toBeTrue();
    });
});

it('renders the workforce analytics report', function (): void {
    Livewire::test(WorkforceAnalytics::class)
        ->assertOk()
        ->assertSee('Workforce analytics');
});
