<?php

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Models\PersonnelAction;
use App\Jurisdictions\Philippines\Government\Models\ServiceRecord;
use App\Jurisdictions\Philippines\Government\Services\AppointmentService;
use App\Jurisdictions\Philippines\Government\Services\PersonnelActionService;
use App\Jurisdictions\Philippines\Government\Services\PlantillaService;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Office;
use App\Models\OrganizationUnit;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'City Government']);

    $this->user = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'HR Officer',
    ]);

    $this->actingAs($this->user);

    app(TenantContext::class)->run($this->company->id, function (): void {
        $this->office = Office::create([
            'company_id' => $this->company->id,
            'name' => 'City Human Resource Management Office',
            'code' => 'CHRMO',
        ]);

        $this->organizationUnit = OrganizationUnit::create([
            'company_id' => $this->company->id,
            'name' => 'Administrative Division',
            'unit_type' => OrganizationUnit::TYPE_DIVISION,
        ]);

        $this->clerkPosition = Position::forceCreate([
            'company_id' => $this->company->id,
            'code' => 'CLK-1',
            'name' => 'Clerk I',
        ]);

        $this->officerPosition = Position::forceCreate([
            'company_id' => $this->company->id,
            'code' => 'AO-1',
            'name' => 'Administrative Officer I',
        ]);

        $plantilla = app(PlantillaService::class);
        $version = $plantilla->createVersion(['version' => '2026-1']);

        $this->sourceItem = $plantilla->addItem($version, [
            'office_id' => $this->office->id,
            'item_number' => 'P-1',
            'position_title' => 'Clerk I',
            'salary_grade' => 6,
            'step' => 1,
            'position_id' => $this->clerkPosition->id,
            'organization_unit_id' => $this->organizationUnit->id,
        ]);

        $this->targetItem = $plantilla->addItem($version, [
            'office_id' => $this->office->id,
            'item_number' => 'P-2',
            'position_title' => 'Administrative Officer I',
            'salary_grade' => 11,
            'step' => 1,
            'position_id' => $this->officerPosition->id,
            'organization_unit_id' => $this->organizationUnit->id,
        ]);

        $plantilla->approveVersion($version, $this->user);

        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $appointments = app(AppointmentService::class);

        $appointment = $appointments->create([
            'employee_id' => $this->employee->id,
            'plantilla_item_id' => $this->sourceItem->id,
            'appointment_type' => AppointmentRecord::TYPE_ORIGINAL,
            'nature' => AppointmentRecord::NATURE_PERMANENT,
            'effectivity_date' => '2026-01-02',
            'status' => AppointmentRecord::STATUS_SERVED,
        ], $this->user);

        $this->initialAppointment = $appointments->transition($appointment, AppointmentRecord::STATUS_VALIDATED, $this->user);

        $this->service = app(PersonnelActionService::class);
    });

    app(TenantContext::class)->set($this->company->id);

    $this->promotionAttributes = fn (array $overrides = []): array => [
        'employee_id' => $this->employee->id,
        'action_type' => AppointmentRecord::TYPE_PROMOTION,
        'current_appointment_id' => $this->initialAppointment->id,
        'target_plantilla_item_id' => $this->targetItem->id,
        'target_position_id' => $this->officerPosition->id,
        'target_office_id' => $this->office->id,
        'reason' => 'Promotion to a vacant Administrative Officer I item',
        'proposed_effective_date' => '2026-03-01',
        ...$overrides,
    ];

    $this->approvedPromotion = function (array $overrides = []): PersonnelAction {
        $action = $this->service->create(($this->promotionAttributes)($overrides), $this->user);

        foreach ([
            PersonnelAction::STATUS_SUBMITTED,
            PersonnelAction::STATUS_UNDER_REVIEW,
            PersonnelAction::STATUS_APPROVED,
        ] as $status) {
            $action = $this->service->transition($action, $status, $this->user);
        }

        return $action;
    };
});

it('creates a draft personnel action', function () {
    $action = $this->service->create(($this->promotionAttributes)(), $this->user);

    expect($action->status)->toBe(PersonnelAction::STATUS_DRAFT)
        ->and($action->company_id)->toBe($this->company->id)
        ->and($action->employee_id)->toBe($this->employee->id)
        ->and($action->action_type)->toBe(AppointmentRecord::TYPE_PROMOTION)
        ->and($action->approved_by)->toBeNull();
});

it('rejects an invalid status transition', function () {
    $action = $this->service->create(($this->promotionAttributes)(), $this->user);

    $this->service->transition($action, PersonnelAction::STATUS_SUBMITTED, $this->user);

    expect(fn () => $this->service->transition(
        $action->refresh(),
        PersonnelAction::STATUS_COMPLETED,
        $this->user,
    ))->toThrow(ValidationException::class);
});

it('walks draft to approved and stamps the approver and effective date', function () {
    $action = $this->service->create(($this->promotionAttributes)(), $this->user);

    $submitted = $this->service->transition($action, PersonnelAction::STATUS_SUBMITTED, $this->user);
    expect($submitted->status)->toBe(PersonnelAction::STATUS_SUBMITTED);

    $reviewed = $this->service->transition($submitted, PersonnelAction::STATUS_UNDER_REVIEW, $this->user);
    expect($reviewed->status)->toBe(PersonnelAction::STATUS_UNDER_REVIEW);

    $approved = $this->service->transition($reviewed, PersonnelAction::STATUS_APPROVED, $this->user);

    expect($approved->status)->toBe(PersonnelAction::STATUS_APPROVED)
        ->and($approved->approved_by)->toBe($this->user->id)
        ->and($approved->approved_at)->not->toBeNull()
        ->and($approved->effective_date?->toDateString())->toBe('2026-03-01');
});

it('completes an approved promotion without disturbing the prior appointment and service record', function () {
    $action = ($this->approvedPromotion)();

    $completed = $this->service->complete($action, $this->user);

    expect($completed->status)->toBe(PersonnelAction::STATUS_COMPLETED);

    $appointments = AppointmentRecord::withoutTenant()
        ->where('employee_id', $this->employee->id)
        ->orderBy('id')
        ->get();

    expect($appointments)->toHaveCount(2);

    $original = $appointments->firstWhere('id', $this->initialAppointment->id);
    $promotion = $appointments->firstWhere('appointment_type', AppointmentRecord::TYPE_PROMOTION);

    expect($original)->not->toBeNull()
        ->and($original->status)->toBe(AppointmentRecord::STATUS_VALIDATED)
        ->and($original->plantilla_item_id)->toBe($this->sourceItem->id)
        ->and($original->applied_at)->not->toBeNull();

    expect($promotion)->not->toBeNull()
        ->and($promotion->status)->toBe(AppointmentRecord::STATUS_VALIDATED)
        ->and($promotion->plantilla_item_id)->toBe($this->targetItem->id)
        ->and($promotion->position_id)->toBe($this->officerPosition->id)
        ->and($promotion->approved_by)->toBe($this->user->id)
        ->and($promotion->effectivity_date->toDateString())->toBe('2026-03-01')
        ->and($promotion->applied_at)->not->toBeNull()
        ->and($promotion->metadata['personnel_action_id'] ?? null)->toBe($action->id);

    $assignments = PositionAssignment::withoutTenant()
        ->where('employee_id', $this->employee->id)
        ->orderBy('effective_from')
        ->get();

    expect($assignments)->toHaveCount(2)
        ->and($assignments->last()->position_id)->toBe($this->officerPosition->id)
        ->and($assignments->last()->effective_from->toDateString())->toBe('2026-03-01');

    $serviceRecords = ServiceRecord::query()
        ->where('employee_id', $this->employee->id)
        ->orderBy('id')
        ->get();

    expect($serviceRecords)->toHaveCount(2)
        ->and($serviceRecords->first()->appointment_id)->toBe($this->initialAppointment->id)
        ->and($serviceRecords->last()->appointment_id)->toBe($promotion->id)
        ->and($serviceRecords->last()->designation)->toBe('Administrative Officer I');

    $employee = $this->employee->fresh();

    expect($employee->position_id)->toBe($this->officerPosition->id)
        ->and((int) $employee->salary_grade)->toBe(11)
        ->and((int) $employee->step)->toBe(1)
        ->and($employee->item_number)->toBe('P-2');
});

it('refuses to complete an approved action without an effective date', function () {
    $action = ($this->approvedPromotion)(['proposed_effective_date' => null]);

    expect($action->effective_date)->toBeNull()
        ->and($action->proposed_effective_date)->toBeNull();

    try {
        $this->service->complete($action, $this->user);
        $this->fail('Completing an action without an effective date should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('effective_date');
    }

    expect($action->refresh()->status)->toBe(PersonnelAction::STATUS_APPROVED);
});

it('scopes personnel actions to their tenant', function () {
    $action = $this->service->create(($this->promotionAttributes)(), $this->user);

    $otherCompany = Company::factory()->create(['name' => 'Other City Government']);

    app(TenantContext::class)->run($otherCompany->id, function () use ($action): void {
        expect(PersonnelAction::query()->count())->toBe(0)
            ->and(PersonnelAction::query()->whereKey($action->id)->exists())->toBeFalse();
    });

    app(TenantContext::class)->run($this->company->id, function () use ($action): void {
        expect(PersonnelAction::query()->whereKey($action->id)->exists())->toBeTrue();
    });
});
