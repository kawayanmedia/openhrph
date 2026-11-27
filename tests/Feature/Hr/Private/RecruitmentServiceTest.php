<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\EmploymentType;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\JobVacancy;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingTask;
use App\Models\Position;
use App\Models\ScreeningResult;
use App\Models\User;
use App\Services\Recruitment\RecruitmentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use KawayanMedia\Government\Models\Applicant;

uses(RefreshDatabase::class);

function recruitmentPipelineApplicant(string $name = 'Maria Clara Santos', string $telephone = '09171234567'): Applicant
{
    return Applicant::create([
        'name' => $name,
        'gender' => 'female',
        'telephone' => $telephone,
        'eligibility' => 'Career Service Professional',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function recruitmentPipelineRequisition(
    RecruitmentService $service,
    User $user,
    Company $company,
    Position $position,
    EmploymentType $employmentType,
    array $attributes = [],
): JobRequisition {
    return $service->createRequisition([
        'company_id' => $company->id,
        'position_id' => $position->id,
        'employment_type_id' => $employmentType->id,
        'reason' => JobRequisition::REASON_NEW_HEADCOUNT,
        'headcount' => 1,
        'salary_min' => 40000,
        'salary_max' => 60000,
        ...$attributes,
    ], $user);
}

function recruitmentPipelineApprovedRequisition(
    RecruitmentService $service,
    User $user,
    Company $company,
    Position $position,
    EmploymentType $employmentType,
): JobRequisition {
    $requisition = recruitmentPipelineRequisition($service, $user, $company, $position, $employmentType);

    foreach ([
        JobRequisition::STATUS_SUBMITTED,
        JobRequisition::STATUS_UNDER_REVIEW,
        JobRequisition::STATUS_APPROVED,
    ] as $status) {
        $requisition = $service->transitionRequisition($requisition, $status, $user);
    }

    return $requisition;
}

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Acme Corporation']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->employmentType = EmploymentType::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Regular',
    ]);

    $this->position = Position::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'SE1',
        'name' => 'Software Engineer',
    ]);

    $this->service = app(RecruitmentService::class);
});

it('creates a draft requisition for the active tenant', function (): void {
    $requisition = recruitmentPipelineRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    expect($requisition->status)->toBe(JobRequisition::STATUS_DRAFT)
        ->and((int) $requisition->company_id)->toBe($this->company->id)
        ->and((int) $requisition->requested_by)->toBe($this->user->id)
        ->and((int) $requisition->position_id)->toBe($this->position->id)
        ->and($requisition->headcount)->toBe(1);
});

it('rejects an invalid requisition transition and records approval details', function (): void {
    $requisition = recruitmentPipelineRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    expect(fn () => $this->service->transitionRequisition(
        $requisition,
        JobRequisition::STATUS_APPROVED,
        $this->user,
    ))->toThrow(ValidationException::class);

    $requisition = $this->service->transitionRequisition($requisition, JobRequisition::STATUS_SUBMITTED, $this->user);
    expect($requisition->status)->toBe(JobRequisition::STATUS_SUBMITTED);

    $requisition = $this->service->transitionRequisition($requisition, JobRequisition::STATUS_UNDER_REVIEW, $this->user);
    $approved = $this->service->transitionRequisition($requisition, JobRequisition::STATUS_APPROVED, $this->user);

    expect($approved->status)->toBe(JobRequisition::STATUS_APPROVED)
        ->and((int) $approved->approved_by)->toBe($this->user->id)
        ->and($approved->approved_at)->not->toBeNull();
});

it('publishes an open vacancy that copies requisition details', function (): void {
    $requisition = recruitmentPipelineApprovedRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    $vacancy = $this->service->publishVacancy($requisition, [
        'title' => 'Senior Software Engineer',
        'summary' => 'Build and maintain the payroll platform.',
    ], $this->user);

    expect($vacancy->status)->toBe(JobVacancy::STATUS_OPEN)
        ->and($vacancy->isOpen())->toBeTrue()
        ->and($vacancy->is_active)->toBeTrue()
        ->and($vacancy->type)->toBe('full_time')
        ->and($vacancy->title)->toBe('Senior Software Engineer')
        ->and((int) $vacancy->requisition_id)->toBe($requisition->id)
        ->and((int) $vacancy->position_id)->toBe($this->position->id)
        ->and((int) $vacancy->employment_type_id)->toBe($this->employmentType->id)
        ->and($vacancy->salary_min)->toBe(40000)
        ->and($vacancy->salary_max)->toBe(60000)
        ->and($vacancy->published_at)->not->toBeNull();
});

it('refuses to publish a vacancy from a requisition that is not approved', function (): void {
    $requisition = recruitmentPipelineRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    expect(fn () => $this->service->publishVacancy($requisition, [], $this->user))
        ->toThrow(ValidationException::class, 'Only an approved requisition can create a vacancy.');
});

it('rejects applications to non-open vacancies and duplicate applicants', function (): void {
    $requisition = recruitmentPipelineApprovedRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    $vacancy = $this->service->publishVacancy($requisition, [], $this->user);
    $applicant = recruitmentPipelineApplicant();

    $application = $this->service->apply($vacancy, $applicant, ['source' => 'careers_page'], $this->user);

    expect($application->status)->toBe(Application::STATUS_RECEIVED)
        ->and((int) $application->applicant_id)->toBe($applicant->id)
        ->and((int) $application->company_id)->toBe($this->company->id)
        ->and($application->metadata)->toMatchArray(['applicant_id' => $applicant->id])
        ->and($application->source)->toBe('careers_page')
        ->and($application->submitted_at)->not->toBeNull();

    expect(fn () => $this->service->apply($vacancy, $applicant, [], $this->user))
        ->toThrow(ValidationException::class);

    $vacancy->forceFill(['status' => JobVacancy::STATUS_CLOSED, 'is_active' => false])->save();

    expect(fn () => $this->service->apply($vacancy, recruitmentPipelineApplicant('Juan Dela Cruz', '09181234567'), [], $this->user))
        ->toThrow(ValidationException::class, 'This vacancy is not open for applications.');
});

it('screens applicants into shortlisted and rejected dispositions', function (): void {
    $requisition = recruitmentPipelineApprovedRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    $vacancy = $this->service->publishVacancy($requisition, [], $this->user);

    $qualifiedApplication = $this->service->apply($vacancy, recruitmentPipelineApplicant(), [], $this->user);
    $disqualifiedApplication = $this->service->apply(
        $vacancy,
        recruitmentPipelineApplicant('Juan Dela Cruz', '09181234567'),
        [],
        $this->user,
    );

    $screening = $this->service->screen($qualifiedApplication, ScreeningResult::RESULT_QUALIFIED, 'Meets all criteria', $this->user);

    expect($screening->result)->toBe(ScreeningResult::RESULT_QUALIFIED)
        ->and((int) $screening->application_id)->toBe($qualifiedApplication->id)
        ->and((int) $screening->reviewer_id)->toBe($this->user->id)
        ->and($screening->reviewed_at)->not->toBeNull()
        ->and($qualifiedApplication->refresh()->status)->toBe(Application::STATUS_SHORTLISTED)
        ->and($qualifiedApplication->disposition_reason)->toBeNull();

    $rejected = $this->service->screen(
        $disqualifiedApplication,
        ScreeningResult::RESULT_DISQUALIFIED,
        'Missing required professional license',
        $this->user,
    );

    expect($rejected->result)->toBe(ScreeningResult::RESULT_DISQUALIFIED)
        ->and($disqualifiedApplication->refresh()->status)->toBe(Application::STATUS_REJECTED)
        ->and($disqualifiedApplication->disposition_reason)->toBe('Missing required professional license');
});

it('runs the full pipeline from interview to hire', function (): void {
    $requisition = recruitmentPipelineApprovedRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    $vacancy = $this->service->publishVacancy($requisition, [], $this->user);
    $application = $this->service->apply($vacancy, recruitmentPipelineApplicant(), [], $this->user);

    $this->service->screen($application, ScreeningResult::RESULT_QUALIFIED, 'Qualified', $this->user);

    $interview = $this->service->scheduleInterview($application, [
        'scheduled_at' => now()->addDays(3),
        'mode' => 'onsite',
        'location' => 'Head Office',
    ], $this->user);

    expect($interview->status)->toBe(Interview::STATUS_SCHEDULED)
        ->and($application->refresh()->status)->toBe(Application::STATUS_INTERVIEW);

    $evaluation = $this->service->recordEvaluation($interview, [
        'scores' => ['technical' => 4, 'culture' => 5],
        'comments' => 'Strong hire',
        'recommendation' => 'hire',
    ], $this->user);

    expect((int) $evaluation->interview_id)->toBe($interview->id)
        ->and((int) $evaluation->reviewer_id)->toBe($this->user->id)
        ->and($evaluation->submitted_at)->not->toBeNull();

    $assessment = $this->service->recordAssessment($application, [
        'assessment_type' => 'technical',
        'score' => 92.5,
        'result' => 'passed',
    ], $this->user);

    expect((int) $assessment->application_id)->toBe($application->id)
        ->and((int) $assessment->assessed_by)->toBe($this->user->id)
        ->and($application->refresh()->status)->toBe(Application::STATUS_ASSESSMENT);

    $recommendation = $this->service->recommend($application, [
        'decision_reference' => 'REC-2026-001',
        'notes' => 'Proceed to offer',
    ], $this->user);

    expect((int) $recommendation->application_id)->toBe($application->id)
        ->and((int) $recommendation->recommended_by)->toBe($this->user->id)
        ->and($application->refresh()->status)->toBe(Application::STATUS_RECOMMENDED);

    $offer = $this->service->issueOffer($application, [
        'salary' => 45000,
        'start_date' => '2026-12-01',
        'terms' => ['probation_months' => 6],
    ], $this->user);

    expect($offer->offer_version)->toBe(1)
        ->and($offer->status)->toBe(JobOffer::STATUS_ISSUED)
        ->and($offer->issued_at)->not->toBeNull()
        ->and($application->refresh()->status)->toBe(Application::STATUS_OFFER);

    $accepted = $this->service->respondToOffer($offer, JobOffer::STATUS_ACCEPTED, $this->user);

    expect($accepted->status)->toBe(JobOffer::STATUS_ACCEPTED)
        ->and($accepted->responded_at)->not->toBeNull();

    $employee = $this->service->hire($application, [
        'start_date' => '2026-12-01',
        'classification' => Employment::CLASSIFICATION_REGULAR,
    ], $this->user);

    expect($employee->first_name)->toBe('Maria')
        ->and($employee->last_name)->toBe('Santos')
        ->and($employee->lifecycle_status)->toBe(Employee::LIFECYCLE_ACTIVE)
        ->and((int) $employee->company_id)->toBe($this->company->id)
        ->and(Employee::query()->count())->toBe(1);

    $employment = Employment::query()->where('employee_id', $employee->id)->sole();

    expect($employment->status)->toBe(Employment::STATUS_ACTIVE)
        ->and((int) $employment->employment_type_id)->toBe($this->employmentType->id)
        ->and($employment->start_date->toDateString())->toBe('2026-12-01')
        ->and(Employment::query()->active()->count())->toBe(1);

    $checklist = OnboardingChecklist::query()->where('employee_id', $employee->id)->sole();

    expect($checklist->tasks()->count())->toBe(7)
        ->and($checklist->tasks()->where('status', OnboardingTask::STATUS_PENDING)->count())->toBe(7);

    expect($application->refresh()->status)->toBe(Application::STATUS_HIRED)
        ->and($application->current_stage)->toBe('hired')
        ->and($application->metadata)->toMatchArray([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
        ]);

    expect($vacancy->refresh()->status)->toBe(JobVacancy::STATUS_FILLED)
        ->and($vacancy->is_active)->toBeFalse();
});

it('requires an accepted offer before hiring unless the offer check is overridden', function (): void {
    $requisition = recruitmentPipelineApprovedRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    $vacancy = $this->service->publishVacancy($requisition, [], $this->user);
    $application = $this->service->apply($vacancy, recruitmentPipelineApplicant('Juan Santos', '09181234567'), [], $this->user);

    expect(fn () => $this->service->hire($application, ['start_date' => '2026-12-01'], $this->user))
        ->toThrow(ValidationException::class, 'The candidate must accept an offer before hire.');

    expect(Employee::query()->count())->toBe(0)
        ->and($application->refresh()->status)->toBe(Application::STATUS_RECEIVED);

    $employee = $this->service->hire($application, [
        'override_offer' => true,
        'start_date' => '2026-12-01',
    ], $this->user);

    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->first_name)->toBe('Juan')
        ->and($employee->last_name)->toBe('Santos')
        ->and($employee->lifecycle_status)->toBe(Employee::LIFECYCLE_ACTIVE)
        ->and($application->refresh()->status)->toBe(Application::STATUS_HIRED);
});

it('isolates requisitions and applications between tenants', function (): void {
    $requisition = recruitmentPipelineApprovedRequisition(
        $this->service,
        $this->user,
        $this->company,
        $this->position,
        $this->employmentType,
    );

    $vacancy = $this->service->publishVacancy($requisition, [], $this->user);
    $application = $this->service->apply($vacancy, recruitmentPipelineApplicant(), [], $this->user);

    $otherCompany = Company::factory()->create(['name' => 'Other Corporation']);
    $otherUser = User::factory()->forCompany($otherCompany)->create();

    $otherRequisition = app(TenantContext::class)->run($otherCompany->id, function () use ($otherCompany, $otherUser, $requisition, $vacancy, $application): JobRequisition {
        expect(JobRequisition::query()->whereKey($requisition->id)->exists())->toBeFalse()
            ->and(JobVacancy::query()->whereKey($vacancy->id)->exists())->toBeFalse()
            ->and(Application::query()->whereKey($application->id)->exists())->toBeFalse()
            ->and(JobRequisition::query()->count())->toBe(0)
            ->and(Application::query()->count())->toBe(0);

        $created = $this->service->createRequisition([
            'reason' => JobRequisition::REASON_EXPANSION,
            'headcount' => 2,
        ], $otherUser);

        expect((int) $created->company_id)->toBe($otherCompany->id)
            ->and(JobRequisition::query()->count())->toBe(1);

        return $created;
    });

    expect(JobRequisition::query()->whereKey($requisition->id)->exists())->toBeTrue()
        ->and(JobRequisition::query()->whereKey($otherRequisition->id)->exists())->toBeFalse()
        ->and(Application::query()->whereKey($application->id)->exists())->toBeTrue()
        ->and(JobRequisition::query()->count())->toBe(1);
});
