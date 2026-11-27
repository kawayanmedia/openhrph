<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Services\Learning\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Learning Co']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->service = app(LearningService::class);

    $this->course = $this->service->createCourse([
        'company_id' => $this->company->id,
        'code' => 'LEAD-01',
        'title' => 'Supervisory Leadership',
        'provider' => 'Civil Service Institute',
    ], $this->user);

    $this->session = $this->service->scheduleSession($this->course, [
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDays(2),
        'capacity' => 1,
    ], $this->user);
});

it('enrolls an employee once and enforces capacity', function (): void {
    $enrollment = $this->service->enroll($this->session, $this->employee, $this->user);

    expect($enrollment->status)->toBe(TrainingEnrollment::STATUS_ENROLLED)
        ->and((int) $enrollment->company_id)->toBe($this->company->id);

    $duplicate = $this->service->enroll($this->session, $this->employee, $this->user);
    expect($duplicate->id)->toBe($enrollment->id);

    $other = Employee::factory()->create(['company_id' => $this->company->id]);

    expect(fn () => $this->service->enroll($this->session, $other, $this->user))
        ->toThrow(ValidationException::class);
});

it('records completion details and validates the status', function (): void {
    $enrollment = $this->service->enroll($this->session, $this->employee, $this->user);

    $completed = $this->service->recordCompletion(
        $enrollment,
        TrainingEnrollment::STATUS_COMPLETED,
        95.5,
        'Excellent participation.',
        $this->user,
    );

    expect($completed->status)->toBe(TrainingEnrollment::STATUS_COMPLETED)
        ->and((float) $completed->score)->toBe(95.5)
        ->and($completed->completion_date?->toDateString())->toBe(now()->toDateString());

    expect(fn () => $this->service->recordCompletion(
        $completed,
        TrainingEnrollment::STATUS_ENROLLED,
        null,
        null,
        $this->user,
    ))->toThrow(ValidationException::class);
});

it('only enrolls into scheduled sessions', function (): void {
    $this->session->forceFill(['status' => 'cancelled'])->save();

    expect(fn () => $this->service->enroll($this->session->refresh(), $this->employee, $this->user))
        ->toThrow(ValidationException::class);
});

it('lists certifications expiring within the reminder window', function (): void {
    $this->service->issueCertification($this->employee, [
        'certification_type' => 'professional',
        'title' => 'Certified Public Accountant',
        'issuer' => 'PRC',
        'issued_at' => now()->subYears(2)->toDateString(),
        'expires_at' => now()->addDays(15)->toDateString(),
    ], $this->user);

    $this->service->issueCertification($this->employee, [
        'certification_type' => 'professional',
        'title' => 'Long-lived licence',
        'expires_at' => now()->addDays(400)->toDateString(),
    ], $this->user);

    $expiring = $this->service->expiringCertifications(30);

    expect($expiring)->toHaveCount(1)
        ->and($expiring->first()->title)->toBe('Certified Public Accountant')
        ->and($expiring->first()->status)->toBe(EmployeeCertification::STATUS_ACTIVE);
});
