<?php

use App\Jurisdictions\Philippines\Government\Admin\Resources\AppointmentAction as AppointmentActionResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\AppointmentDocument as AppointmentDocumentResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\AppointmentRecord as AppointmentRecordResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\AssumptionRecord as AssumptionRecordResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\HrmpsbCommittee as HrmpsbCommitteeResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\HrmpsbMember as HrmpsbMemberResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\PersonnelAction as PersonnelActionResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\PlantillaItem as PlantillaItemResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\PlantillaVersion as PlantillaVersionResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\QualificationScreening as QualificationScreeningResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\QualificationStandard as QualificationStandardResource;
use App\Jurisdictions\Philippines\Government\Admin\Resources\RecruitmentDeliberation as RecruitmentDeliberationResource;
use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Support\ResourceRegistry;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $this->company = Company::factory()->create(['name' => 'City Government']);

    $this->admin = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'Government Admin',
    ]);

    $this->actingAs($this->admin);

    app(TenantContext::class)->set($this->company->id);

    $this->governmentResources = [
        'plantilla-version' => PlantillaVersionResource::class,
        'plantilla-item' => PlantillaItemResource::class,
        'qualification-standard' => QualificationStandardResource::class,
        'qualification-screening' => QualificationScreeningResource::class,
        'appointment-record' => AppointmentRecordResource::class,
        'appointment-document' => AppointmentDocumentResource::class,
        'appointment-action' => AppointmentActionResource::class,
        'assumption-record' => AssumptionRecordResource::class,
        'personnel-action' => PersonnelActionResource::class,
        'hrmpsb-committee' => HrmpsbCommitteeResource::class,
        'hrmpsb-member' => HrmpsbMemberResource::class,
        'recruitment-deliberation' => RecruitmentDeliberationResource::class,
    ];
});

it('registers the government resources and resolves their fields', function () {
    app(TenantContext::class)->run($this->company->id, function (): void {
        foreach ($this->governmentResources as $uriKey => $class) {
            expect(ResourceRegistry::find($uriKey))->toBe($class);

            $fields = (new $class)->fields(request());

            expect($fields)->toBeArray()
                ->and($fields)->not->toBeEmpty();
        }
    });
});

it('renders the government administration screens', function () {
    $renderable = [
        'plantilla-version',
        'qualification-standard',
        'appointment-record',
        'personnel-action',
        'hrmpsb-committee',
    ];

    foreach ($renderable as $uriKey) {
        app(TenantContext::class)->run($this->company->id, function () use ($uriKey): void {
            Livewire::test(AdminIndex::class, ['resource' => $uriKey])->assertOk();
            Livewire::test(AdminForm::class, ['resource' => $uriKey])->assertOk();
        });
    }
});
