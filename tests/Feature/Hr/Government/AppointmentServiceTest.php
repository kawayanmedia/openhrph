<?php

use App\Exceptions\Tenancy\CrossTenantWriteException;
use App\Jurisdictions\Philippines\Government\Models\AppointmentAction;
use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Models\AssumptionRecord;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Models\ServiceRecord;
use App\Jurisdictions\Philippines\Government\Services\AppointmentService;
use App\Jurisdictions\Philippines\Government\Services\PlantillaService;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentChange;
use App\Models\Office;
use App\Models\OrganizationUnit;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Run a callback inside the given tenant's context.
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function withinGovernmentTenant(Company $company, Closure $callback): mixed
{
    return app(TenantContext::class)->run($company->id, $callback);
}

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->forCompany($this->company)->create();

    $this->actingAs($this->user);

    $this->service = app(AppointmentService::class);
    $this->plantilla = app(PlantillaService::class);

    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->otherEmployee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->position = Position::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'POS-CLERK',
        'name' => 'Clerk III',
    ]);

    $this->office = Office::create([
        'company_id' => $this->company->id,
        'code' => 'MUN-01',
        'name' => 'Municipal Hall',
    ]);

    $this->organizationUnit = OrganizationUnit::create([
        'company_id' => $this->company->id,
        'name' => 'Human Resource Management Office',
        'unit_type' => OrganizationUnit::TYPE_DIVISION,
    ]);

    [$this->version, $this->item] = withinGovernmentTenant($this->company, function () {
        $version = $this->plantilla->createVersion(['version' => '2026-1']);

        $item = $this->plantilla->addItem($version, [
            'office_id' => $this->office->id,
            'organization_unit_id' => $this->organizationUnit->id,
            'item_number' => 'ITEM-1',
            'position_title' => 'Clerk III',
            'salary_grade' => 6,
            'step' => 1,
            'position_id' => $this->position->id,
        ]);

        return [$version, $item];
    });

    $this->appointmentAttributes = fn (array $overrides = []): array => [
        'employee_id' => $this->employee->id,
        'plantilla_item_id' => $this->item->id,
        'appointment_type' => AppointmentRecord::TYPE_ORIGINAL,
        'nature' => AppointmentRecord::NATURE_PERMANENT,
        'legal_basis' => 'Sec. 20, Book V, EO 292',
        'reference_number' => 'APPT-0001',
        'effectivity_date' => '2026-01-02',
    ] + $overrides;

    $this->createAppointment = fn (array $overrides = []): AppointmentRecord => withinGovernmentTenant(
        $this->company,
        fn (): AppointmentRecord => $this->service->create(($this->appointmentAttributes)($overrides), $this->user),
    );

    $this->issuedAppointment = fn (array $overrides = []): AppointmentRecord => withinGovernmentTenant(
        $this->company,
        function () use ($overrides): AppointmentRecord {
            $appointment = $this->service->create(($this->appointmentAttributes)($overrides), $this->user);

            $appointment = $this->service->transition($appointment, AppointmentRecord::STATUS_FOR_REVIEW, $this->user);
            $appointment = $this->service->transition($appointment, AppointmentRecord::STATUS_APPROVED_FOR_ISSUANCE, $this->user);

            return $this->service->transition($appointment, AppointmentRecord::STATUS_ISSUED, $this->user);
        },
    );
});

it('defaults position, plantilla version and office from the plantilla item', function () {
    $appointment = ($this->createAppointment)();

    expect($appointment->company_id)->toBe($this->company->id)
        ->and($appointment->status)->toBe(AppointmentRecord::STATUS_DRAFT)
        ->and($appointment->position_id)->toBe($this->position->id)
        ->and($appointment->plantilla_version_id)->toBe($this->version->id)
        ->and($appointment->office_id)->toBe($this->office->id);
});

it('rejects a plantilla item already occupied by another employee', function () {
    PlantillaItem::query()->whereKey($this->item->id)->update(['employee_id' => $this->otherEmployee->id]);

    expect(fn () => ($this->createAppointment)())
        ->toThrow(ValidationException::class, 'This plantilla item is already occupied.');
});

it('rejects a plantilla item already referenced by another active appointment', function () {
    ($this->createAppointment)(['employee_id' => $this->otherEmployee->id]);

    expect(fn () => ($this->createAppointment)())
        ->toThrow(ValidationException::class, 'Another active appointment already references this plantilla item.');
});

it('rejects an invalid transition and stamps the approval chain on a valid path', function () {
    $appointment = ($this->createAppointment)();

    expect(fn () => withinGovernmentTenant(
        $this->company,
        fn () => $this->service->transition($appointment, AppointmentRecord::STATUS_ISSUED, $this->user),
    ))->toThrow(ValidationException::class);

    withinGovernmentTenant($this->company, function () use ($appointment): void {
        expect($appointment->fresh()->status)->toBe(AppointmentRecord::STATUS_DRAFT);
    });

    $issued = withinGovernmentTenant($this->company, function () use ($appointment): AppointmentRecord {
        $forReview = $this->service->transition($appointment, AppointmentRecord::STATUS_FOR_REVIEW, $this->user);
        $approved = $this->service->transition($forReview, AppointmentRecord::STATUS_APPROVED_FOR_ISSUANCE, $this->user);

        expect($approved->status)->toBe(AppointmentRecord::STATUS_APPROVED_FOR_ISSUANCE)
            ->and($approved->approved_by)->toBe($this->user->id)
            ->and($approved->approved_at)->not->toBeNull()
            ->and($approved->issued_at)->toBeNull();

        return $this->service->transition($approved, AppointmentRecord::STATUS_ISSUED, $this->user);
    });

    expect($issued->status)->toBe(AppointmentRecord::STATUS_ISSUED)
        ->and($issued->issued_at)->not->toBeNull()
        ->and($issued->served_at)->toBeNull()
        ->and($issued->applied_at)->toBeNull();
});

it('records the assumption and applies the placement exactly once', function () {
    $appointment = ($this->issuedAppointment)();

    withinGovernmentTenant($this->company, function () use ($appointment): void {
        $assumption = $this->service->recordAssumption($appointment, [
            'assumption_date' => '2026-01-05',
            'oath_date' => '2026-01-04',
        ], $this->user);

        expect($assumption->appointment_id)->toBe($appointment->id)
            ->and($assumption->assumption_date->toDateString())->toBe('2026-01-05')
            ->and(AssumptionRecord::query()->count())->toBe(1);

        $applied = $appointment->fresh();

        expect($applied->status)->toBe(AppointmentRecord::STATUS_SERVED)
            ->and($applied->assumption_date->toDateString())->toBe('2026-01-05')
            ->and($applied->served_at)->not->toBeNull()
            ->and($applied->applied_at)->not->toBeNull();

        expect(PositionAssignment::query()->where('employee_id', $this->employee->id)->count())->toBe(1)
            ->and(EmploymentChange::query()->where('employee_id', $this->employee->id)->count())->toBe(1)
            ->and(ServiceRecord::query()->where('appointment_id', $appointment->id)->count())->toBe(1);

        $item = PlantillaItem::query()->findOrFail($this->item->id);

        expect($item->employee_id)->toBe($this->employee->id)
            ->and($item->is_vacant)->toBeFalse()
            ->and($item->status)->toBe(PlantillaItem::STATUS_FILLED);

        $serviceRecord = ServiceRecord::query()->where('appointment_id', $appointment->id)->sole();

        expect($serviceRecord->employee_id)->toBe($this->employee->id)
            ->and($serviceRecord->plantilla_item_id)->toBe($item->id)
            ->and($serviceRecord->designation)->toBe('Clerk III');

        $employee = $this->employee->fresh();

        expect($employee->position_id)->toBe($this->position->id)
            ->and((int) $employee->salary_grade)->toBe(6)
            ->and((int) $employee->step)->toBe(1)
            ->and($employee->item_number)->toBe('ITEM-1');
    });
});

it('does not duplicate the placement when applied again', function () {
    $appointment = ($this->issuedAppointment)();

    $appliedAt = withinGovernmentTenant($this->company, function () use ($appointment) {
        $this->service->recordAssumption($appointment, ['assumption_date' => '2026-01-05'], $this->user);

        return $appointment->fresh()->applied_at;
    });

    expect($appliedAt)->not->toBeNull();

    withinGovernmentTenant($this->company, function () use ($appointment, $appliedAt): void {
        $this->service->applyPlacement($appointment->fresh(), $this->user);
        $this->service->applyPlacement($appointment->fresh(), $this->user);

        expect($appointment->fresh()->applied_at->equalTo($appliedAt))->toBeTrue()
            ->and(PositionAssignment::query()->where('employee_id', $this->employee->id)->count())->toBe(1)
            ->and(EmploymentChange::query()->where('employee_id', $this->employee->id)->count())->toBe(1)
            ->and(ServiceRecord::query()->where('appointment_id', $appointment->id)->count())->toBe(1)
            ->and(AssumptionRecord::query()->where('appointment_id', $appointment->id)->count())->toBe(1);
    });
});

it('validates a submitted appointment on csc approval and applies the placement', function () {
    $appointment = ($this->issuedAppointment)();

    withinGovernmentTenant($this->company, function () use ($appointment): void {
        $submitted = $this->service->transition($appointment, AppointmentRecord::STATUS_SERVED, $this->user);
        $submitted = $this->service->transition($submitted, AppointmentRecord::STATUS_SUBMITTED_TO_CSC, $this->user);

        $action = $this->service->recordAction($submitted, AppointmentAction::TYPE_CSC_APPROVAL, [
            'action_date' => '2026-02-02',
            'reference_number' => 'CSC-2026-001',
        ], $this->user);

        expect($action->action_type)->toBe(AppointmentAction::TYPE_CSC_APPROVAL)
            ->and($action->appointment_id)->toBe($appointment->id)
            ->and($action->recorded_by)->toBe($this->user->id)
            ->and(AppointmentAction::query()->count())->toBe(1);

        $validated = $appointment->fresh();

        expect($validated->status)->toBe(AppointmentRecord::STATUS_VALIDATED)
            ->and($validated->csc_action_at)->not->toBeNull()
            ->and($validated->applied_at)->not->toBeNull()
            ->and(PositionAssignment::query()->where('employee_id', $this->employee->id)->count())->toBe(1)
            ->and(ServiceRecord::query()->where('appointment_id', $appointment->id)->count())->toBe(1)
            ->and(PlantillaItem::query()->findOrFail($this->item->id)->employee_id)->toBe($this->employee->id);
    });
});

it('returns a submitted appointment on csc return without applying the placement', function () {
    $appointment = ($this->issuedAppointment)();

    withinGovernmentTenant($this->company, function () use ($appointment): void {
        $submitted = $this->service->transition($appointment, AppointmentRecord::STATUS_SERVED, $this->user);
        $submitted = $this->service->transition($submitted, AppointmentRecord::STATUS_SUBMITTED_TO_CSC, $this->user);

        $this->service->recordAction($submitted, AppointmentAction::TYPE_CSC_RETURN, [
            'action_date' => '2026-02-03',
            'remarks' => 'Missing attachment',
        ], $this->user);

        $returned = $appointment->fresh();

        expect($returned->status)->toBe(AppointmentRecord::STATUS_RETURNED)
            ->and($returned->csc_action_at)->not->toBeNull()
            ->and($returned->applied_at)->toBeNull()
            ->and(PositionAssignment::query()->where('employee_id', $this->employee->id)->count())->toBe(0)
            ->and(EmploymentChange::query()->where('employee_id', $this->employee->id)->count())->toBe(0)
            ->and(ServiceRecord::query()->where('appointment_id', $appointment->id)->count())->toBe(0)
            ->and(PlantillaItem::query()->findOrFail($this->item->id)->employee_id)->toBeNull();
    });
});

it('refuses to apply a cancelled appointment', function () {
    $appointment = ($this->createAppointment)();

    withinGovernmentTenant($this->company, function () use ($appointment): void {
        $cancelled = $this->service->transition($appointment, AppointmentRecord::STATUS_CANCELLED, $this->user);

        expect($cancelled->status)->toBe(AppointmentRecord::STATUS_CANCELLED);

        expect(fn () => $this->service->applyPlacement($cancelled, $this->user))
            ->toThrow(ValidationException::class);

        expect($appointment->fresh()->applied_at)->toBeNull()
            ->and(PositionAssignment::query()->where('employee_id', $this->employee->id)->count())->toBe(0)
            ->and(ServiceRecord::query()->where('appointment_id', $appointment->id)->count())->toBe(0);
    });
});

it('hides appointments from another tenant', function () {
    ($this->createAppointment)();

    $otherCompany = Company::factory()->create();

    expect(withinGovernmentTenant($otherCompany, fn () => AppointmentRecord::query()->count()))->toBe(0)
        ->and(withinGovernmentTenant($this->company, fn () => AppointmentRecord::query()->count()))->toBe(1)
        ->and(AppointmentRecord::withoutTenant()->count())->toBe(1);
});

it('rejects writing an appointment into another tenant', function () {
    $appointment = ($this->createAppointment)();

    $otherCompany = Company::factory()->create();

    expect(fn () => withinGovernmentTenant($otherCompany, function () use ($appointment, $otherCompany): void {
        $appointment->fresh()->forceFill(['company_id' => $otherCompany->id])->save();
    }))->toThrow(CrossTenantWriteException::class);
});
