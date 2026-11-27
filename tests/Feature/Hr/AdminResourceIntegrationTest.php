<?php

use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmploymentChange;
use App\Models\OrganizationUnit;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Integration Co']);

    $this->admin = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'Admin User',
        'email' => 'integration-admin@example.com',
    ]);

    $this->actingAs($this->admin);

    $this->department = app(TenantContext::class)->run($this->company->id, fn () => OrganizationUnit::create([
        'company_id' => $this->company->id,
        'name' => 'Human Resources',
        'code' => 'HR',
        'unit_type' => OrganizationUnit::TYPE_DEPARTMENT,
    ]));
});

it('renders the index and create pages for every new HR resource', function () {
    $resources = [
        'organization-unit', 'location', 'cost-center', 'position-requirement',
        'position-assignment', 'reporting-relationship', 'job-description',
        'document', 'workflow-definition', 'workflow-delegation',
    ];

    foreach ($resources as $resource) {
        Livewire::test(AdminIndex::class, ['resource' => $resource])->assertOk();
        Livewire::test(AdminForm::class, ['resource' => $resource])->assertOk();
    }

    Livewire::test(AdminIndex::class, ['resource' => 'audit-event'])->assertOk();
});

it('creates an organization unit through the generic admin form', function () {
    Livewire::test(AdminForm::class, ['resource' => 'organization-unit'])
        ->set('form.name', 'Finance Division')
        ->set('form.code', 'FIN')
        ->set('form.unit_type', OrganizationUnit::TYPE_DIVISION)
        ->set('form.parent', $this->department->id)
        ->set('form.is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $unit = OrganizationUnit::query()->where('code', 'FIN')->firstOrFail();

    expect($unit->depth)->toBe(1)
        ->and($unit->path)->toBe($this->department->id.'/'.$unit->id);
});

it('validates workflow definition configuration before saving', function () {
    Livewire::test(AdminForm::class, ['resource' => 'workflow-definition'])
        ->set('form.code', 'bad-workflow')
        ->set('form.name', 'Bad workflow')
        ->set('form.version', 1)
        ->set('form.configuration', '{"steps":[]}')
        ->set('form.is_active', true)
        ->call('save')
        ->assertHasErrors('configuration');

    expect(WorkflowDefinition::query()->where('code', 'bad-workflow')->exists())->toBeFalse();
});

it('creates a valid workflow definition', function () {
    Livewire::test(AdminForm::class, ['resource' => 'workflow-definition'])
        ->set('form.code', 'leave-review')
        ->set('form.name', 'Leave review')
        ->set('form.version', 1)
        ->set('form.configuration', json_encode([
            'steps' => [[
                'key' => 'review',
                'name' => 'Review',
                'mode' => 'all',
                'assignees' => [['type' => 'group', 'value' => 'HR']],
            ]],
        ]))
        ->set('form.is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(WorkflowDefinition::query()->where('code', 'leave-review')->exists())->toBeTrue();
});

it('creates a document and records an immutable version for the upload', function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);

    Livewire::test(AdminForm::class, ['resource' => 'document'])
        ->set('form.title', 'Employment Contract')
        ->set('form.category', Document::CATEGORY_CONTRACT)
        ->set('form.classification', Document::CLASSIFICATION_INTERNAL)
        ->set('form.status', Document::STATUS_ACTIVE)
        ->set('form.file', [UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf')])
        ->call('save')
        ->assertHasNoErrors();

    $document = Document::query()->where('title', 'Employment Contract')->firstOrFail();

    expect($document->versions()->count())->toBe(1)
        ->and($document->versions()->first()->file_name)->toBe('contract.pdf')
        ->and($document->uploaded_by)->toBe($this->admin->id);
});

it('applies a position assignment created through the admin form', function () {
    [$position, $employee] = app(TenantContext::class)->run($this->company->id, function () {
        $position = Position::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'HR Officer',
            'code' => 'HRO',
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        return [$position, $employee];
    });

    Livewire::test(AdminForm::class, ['resource' => 'position-assignment'])
        ->set('form.employee', $employee->id)
        ->set('form.position', $position->id)
        ->set('form.organizationUnit', $this->department->id)
        ->set('form.assignment_type', PositionAssignment::TYPE_REGULAR)
        ->set('form.effective_from', '2026-01-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(PositionAssignment::query()->where('employee_id', $employee->id)->count())->toBe(1)
        ->and($employee->refresh()->position_id)->toBe($position->id)
        ->and(EmploymentChange::query()->where('employee_id', $employee->id)->count())->toBe(1);
});
