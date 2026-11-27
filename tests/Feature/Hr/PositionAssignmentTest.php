<?php

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentChange;
use App\Models\OrganizationUnit;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\ReportingRelationship;
use App\Models\User;
use App\Services\Organization\PositionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->forCompany($this->company)->create();

    $this->actingAs($this->user);

    $this->service = app(PositionAssignmentService::class);

    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->position = Position::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'POS-001',
        'name' => 'Administrative Officer',
    ]);

    $this->organizationUnit = OrganizationUnit::create([
        'company_id' => $this->company->id,
        'name' => 'Administrative Division',
        'unit_type' => OrganizationUnit::TYPE_DIVISION,
    ]);

    $this->startDate = Carbon::now()->subMonths(6)->startOfDay();
});

it('creates an active assignment and projects it onto the employee', function () {
    $assignment = $this->service->assign($this->employee, [
        'position_id' => $this->position->id,
        'organization_unit_id' => $this->organizationUnit->id,
        'assignment_type' => PositionAssignment::TYPE_REGULAR,
        'effective_from' => $this->startDate->toDateString(),
    ], $this->user);

    expect($assignment->status)->toBe(PositionAssignment::STATUS_ACTIVE)
        ->and($assignment->employee_id)->toBe($this->employee->id)
        ->and($assignment->effective_from->toDateString())->toBe($this->startDate->toDateString())
        ->and($assignment->effective_until)->toBeNull();

    $employee = $this->employee->fresh();

    expect($employee->position_id)->toBe($this->position->id)
        ->and($employee->position)->toBe('Administrative Officer')
        ->and($employee->organization_unit_id)->toBe($this->organizationUnit->id);

    $change = EmploymentChange::query()->where('employee_id', $this->employee->id)->sole();

    expect($change->change_type)->toBe(EmploymentChange::TYPE_HIRE)
        ->and($change->effective_date->toDateString())->toBe($this->startDate->toDateString())
        ->and($change->from_position_id)->toBeNull()
        ->and($change->to_position_id)->toBe($this->position->id)
        ->and($change->to_organization_unit_id)->toBe($this->organizationUnit->id);

    expect(AuditEvent::withoutTenant()
        ->where('action', 'position_assignment.created')
        ->where('subject_type', $assignment->getMorphClass())
        ->where('subject_id', $assignment->id)
        ->exists())->toBeTrue();
});

it('closes the previous assignment the day before and records the movement', function () {
    $first = $this->service->assign($this->employee, [
        'position_id' => $this->position->id,
        'organization_unit_id' => $this->organizationUnit->id,
        'effective_from' => $this->startDate->toDateString(),
    ], $this->user);

    $transferDate = Carbon::now()->subMonths(3)->startOfDay();

    $otherUnit = OrganizationUnit::create([
        'company_id' => $this->company->id,
        'name' => 'Field Office',
        'unit_type' => OrganizationUnit::TYPE_OFFICE,
    ]);

    $second = $this->service->assign($this->employee, [
        'position_id' => $this->position->id,
        'organization_unit_id' => $otherUnit->id,
        'effective_from' => $transferDate->toDateString(),
    ], $this->user);

    $first->refresh();

    expect($first->effective_until->toDateString())->toBe($transferDate->copy()->subDay()->toDateString())
        ->and($first->status)->toBe(PositionAssignment::STATUS_ENDED)
        ->and($second->status)->toBe(PositionAssignment::STATUS_ACTIVE)
        ->and($second->effective_until)->toBeNull();

    $transfer = EmploymentChange::query()
        ->where('employee_id', $this->employee->id)
        ->where('change_type', EmploymentChange::TYPE_TRANSFER)
        ->sole();

    expect($transfer->effective_date->toDateString())->toBe($transferDate->toDateString())
        ->and($transfer->from_organization_unit_id)->toBe($this->organizationUnit->id)
        ->and($transfer->to_organization_unit_id)->toBe($otherUnit->id);

    $otherPosition = Position::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'POS-002',
        'name' => 'Senior Administrative Officer',
    ]);

    $promotionDate = Carbon::now()->subMonth()->startOfDay();

    $this->service->assign($this->employee, [
        'position_id' => $otherPosition->id,
        'organization_unit_id' => $otherUnit->id,
        'effective_from' => $promotionDate->toDateString(),
    ], $this->user);

    $promotion = EmploymentChange::query()
        ->where('employee_id', $this->employee->id)
        ->where('change_type', EmploymentChange::TYPE_PROMOTION)
        ->sole();

    expect($promotion->effective_date->toDateString())->toBe($promotionDate->toDateString())
        ->and($promotion->from_position_id)->toBe($this->position->id)
        ->and($promotion->to_position_id)->toBe($otherPosition->id)
        ->and(EmploymentChange::query()->where('employee_id', $this->employee->id)->count())->toBe(3);
});

it('rejects a second assignment that starts on the same effective date', function () {
    $this->service->assign($this->employee, [
        'position_id' => $this->position->id,
        'organization_unit_id' => $this->organizationUnit->id,
        'effective_from' => $this->startDate->toDateString(),
    ], $this->user);

    expect(fn () => $this->service->assign($this->employee, [
        'position_id' => $this->position->id,
        'organization_unit_id' => $this->organizationUnit->id,
        'effective_from' => $this->startDate->toDateString(),
    ], $this->user))->toThrow(ValidationException::class);

    expect(PositionAssignment::query()->where('employee_id', $this->employee->id)->count())->toBe(1)
        ->and(EmploymentChange::query()->where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('ends an assignment and marks it ended', function () {
    $assignment = $this->service->assign($this->employee, [
        'position_id' => $this->position->id,
        'organization_unit_id' => $this->organizationUnit->id,
        'effective_from' => $this->startDate->toDateString(),
    ], $this->user);

    $endDate = Carbon::now()->subMonth()->startOfDay();

    $ended = $this->service->end($assignment, $endDate->toDateString(), 'Resigned', $this->user);

    expect($ended->effective_until->toDateString())->toBe($endDate->toDateString())
        ->and($ended->status)->toBe(PositionAssignment::STATUS_ENDED)
        ->and($ended->notes)->toBe('Resigned');

    expect(AuditEvent::withoutTenant()
        ->where('action', 'position_assignment.ended')
        ->where('subject_id', $assignment->id)
        ->exists())->toBeTrue();
});

it('records a reporting relationship and rejects self supervision', function () {
    $supervisor = Employee::factory()->create(['company_id' => $this->company->id]);

    $relationship = $this->service->recordReportingRelationship($this->employee, $supervisor, [
        'effective_from' => $this->startDate->toDateString(),
    ]);

    expect($relationship->supervisor_employee_id)->toBe($supervisor->id)
        ->and($relationship->relationship_type)->toBe(ReportingRelationship::TYPE_SUPERVISOR)
        ->and($this->employee->fresh()->immediate_head_employee_id)->toBe($supervisor->id);

    expect(fn () => $this->service->recordReportingRelationship($this->employee, $this->employee))
        ->toThrow(ValidationException::class);

    expect($this->employee->fresh()->immediate_head_employee_id)->toBe($supervisor->id);
});
