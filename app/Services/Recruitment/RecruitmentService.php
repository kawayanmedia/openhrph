<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\Assessment;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\Interview;
use App\Models\InterviewEvaluation;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\JobVacancy;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingTask;
use App\Models\ScreeningResult;
use App\Models\SelectionRecommendation;
use App\Models\User;
use App\Services\Employment\EmploymentService;
use App\Services\Hr\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use KawayanMedia\Government\Models\Applicant;

/**
 * Private-sector requisition-to-hire pipeline.
 *
 * An applicant profile is converted into an employee at hire time; the
 * application is linked to the new employee through metadata so both records
 * remain traceable without duplicating personal data.
 */
class RecruitmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EmploymentService $employments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRequisition(array $attributes, ?User $actor = null): JobRequisition
    {
        return DB::transaction(function () use ($attributes, $actor): JobRequisition {
            $requisition = JobRequisition::create([
                ...$attributes,
                'company_id' => $attributes['company_id'] ?? app(TenantContext::class)->id(),
                'requested_by' => $attributes['requested_by'] ?? $actor?->getKey(),
                'status' => $attributes['status'] ?? JobRequisition::STATUS_DRAFT,
            ]);

            $this->audit->record('recruitment.requisition_created', $requisition, after: $requisition->getAttributes(), metadata: [
                'actor_id' => $actor?->getKey(),
            ]);

            return $requisition->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transitionRequisition(JobRequisition $requisition, string $status, User $actor, array $attributes = []): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $status, $actor, $attributes): JobRequisition {
            $requisition = JobRequisition::withoutTenant()
                ->whereKey($requisition->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $requisition->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "A requisition in '{$requisition->status}' cannot move to '{$status}'.",
                ]);
            }

            $before = $requisition->getAttributes();
            $changes = [...$attributes, 'status' => $status];

            if ($status === JobRequisition::STATUS_APPROVED) {
                $changes += ['approved_by' => $actor->getKey(), 'approved_at' => now()];
            }

            $requisition->forceFill($changes)->save();

            $this->audit->record('recruitment.requisition_transitioned', $requisition, before: $before, after: $requisition->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
                'to' => $status,
            ]);

            return $requisition->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function publishVacancy(JobRequisition $requisition, array $attributes, User $actor): JobVacancy
    {
        return DB::transaction(function () use ($requisition, $attributes, $actor): JobVacancy {
            $requisition = JobRequisition::withoutTenant()->whereKey($requisition->getKey())->firstOrFail();

            if ($requisition->status !== JobRequisition::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Only an approved requisition can create a vacancy.',
                ]);
            }

            $vacancy = JobVacancy::create([
                ...$attributes,
                'company_id' => $requisition->company_id,
                'requisition_id' => $requisition->getKey(),
                'position_id' => $attributes['position_id'] ?? $requisition->position_id,
                'employment_type_id' => $attributes['employment_type_id'] ?? $requisition->employment_type_id,
                'location_id' => $attributes['location_id'] ?? $requisition->location_id,
                'salary_min' => $attributes['salary_min'] ?? $requisition->salary_min,
                'salary_max' => $attributes['salary_max'] ?? $requisition->salary_max,
                'title' => $attributes['title'] ?? $requisition->position?->name ?? 'Vacancy',
                'type' => $attributes['type'] ?? 'full_time',
                'summary' => $attributes['summary'] ?? '',
                'salary' => $attributes['salary'] ?? $this->salaryLabel($requisition),
                'shift' => $attributes['shift'] ?? '',
                'status' => JobVacancy::STATUS_OPEN,
                'is_active' => true,
                'published_at' => now(),
            ]);

            $this->audit->record('recruitment.vacancy_published', $vacancy, after: $vacancy->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
                'requisition_id' => $requisition->getKey(),
            ]);

            return $vacancy->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function apply(JobVacancy $vacancy, Applicant|int $applicant, array $attributes, User $actor): Application
    {
        $applicantId = $applicant instanceof Applicant ? $applicant->getKey() : $applicant;

        return DB::transaction(function () use ($vacancy, $applicantId, $attributes, $actor): Application {
            $vacancy = JobVacancy::withoutTenant()->whereKey($vacancy->getKey())->firstOrFail();

            if (! $vacancy->isOpen()) {
                throw ValidationException::withMessages([
                    'job_vacancy_id' => 'This vacancy is not open for applications.',
                ]);
            }

            $exists = Application::withoutTenant()
                ->where('job_vacancy_id', $vacancy->getKey())
                ->where('applicant_id', $applicantId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'applicant_id' => 'This applicant already has an application for the vacancy.',
                ]);
            }

            $application = Application::create([
                ...$attributes,
                'company_id' => $vacancy->company_id,
                'job_vacancy_id' => $vacancy->getKey(),
                'applicant_id' => $applicantId,
                'status' => Application::STATUS_RECEIVED,
                'submitted_at' => $attributes['submitted_at'] ?? now(),
                'consent_at' => $attributes['consent_at'] ?? now(),
                'metadata' => ['applicant_id' => $applicantId],
            ]);

            $this->audit->record('recruitment.application_received', $application, after: $application->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $application->refresh();
        });
    }

    public function screen(Application $application, string $result, ?string $notes, User $actor, ?string $criteriaVersion = null): ScreeningResult
    {
        return DB::transaction(function () use ($application, $result, $notes, $actor, $criteriaVersion): ScreeningResult {
            $application = Application::withoutTenant()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            $screening = ScreeningResult::create([
                'company_id' => $application->company_id,
                'application_id' => $application->getKey(),
                'criteria_version' => $criteriaVersion,
                'result' => $result,
                'notes' => $notes,
                'reviewer_id' => $actor->getKey(),
                'reviewed_at' => now(),
            ]);

            $application->forceFill([
                'status' => $result === ScreeningResult::RESULT_QUALIFIED
                    ? Application::STATUS_SHORTLISTED
                    : Application::STATUS_REJECTED,
                'disposition_reason' => $result === ScreeningResult::RESULT_QUALIFIED ? null : $notes,
                'current_stage' => 'screening',
            ])->save();

            $this->audit->record('recruitment.application_screened', $application, after: [
                'result' => $result,
                'screening_id' => $screening->getKey(),
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $screening->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function scheduleInterview(Application $application, array $attributes, User $actor): Interview
    {
        return DB::transaction(function () use ($application, $attributes, $actor): Interview {
            $interview = Interview::create([
                ...$attributes,
                'company_id' => $application->company_id,
                'application_id' => $application->getKey(),
                'status' => $attributes['status'] ?? Interview::STATUS_SCHEDULED,
            ]);

            $application->forceFill([
                'status' => Application::STATUS_INTERVIEW,
                'current_stage' => 'interview',
            ])->save();

            $this->audit->record('recruitment.interview_scheduled', $interview, after: $interview->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $interview->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordEvaluation(Interview $interview, array $attributes, User $actor): InterviewEvaluation
    {
        return DB::transaction(function () use ($interview, $attributes, $actor): InterviewEvaluation {
            $evaluation = InterviewEvaluation::create([
                ...$attributes,
                'company_id' => $interview->company_id,
                'interview_id' => $interview->getKey(),
                'reviewer_id' => $attributes['reviewer_id'] ?? $actor->getKey(),
                'submitted_at' => now(),
            ]);

            $this->audit->record('recruitment.interview_evaluated', $interview, after: [
                'evaluation_id' => $evaluation->getKey(),
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $evaluation->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordAssessment(Application $application, array $attributes, User $actor): Assessment
    {
        return DB::transaction(function () use ($application, $attributes, $actor): Assessment {
            $assessment = Assessment::create([
                ...$attributes,
                'company_id' => $application->company_id,
                'application_id' => $application->getKey(),
                'assessed_by' => $attributes['assessed_by'] ?? $actor->getKey(),
                'assessed_at' => $attributes['assessed_at'] ?? now(),
            ]);

            $application->forceFill([
                'status' => Application::STATUS_ASSESSMENT,
                'current_stage' => 'assessment',
            ])->save();

            $this->audit->record('recruitment.assessment_recorded', $assessment, after: $assessment->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $assessment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recommend(Application $application, array $attributes, User $actor): SelectionRecommendation
    {
        return DB::transaction(function () use ($application, $attributes, $actor): SelectionRecommendation {
            $recommendation = SelectionRecommendation::create([
                ...$attributes,
                'company_id' => $application->company_id,
                'job_vacancy_id' => $application->job_vacancy_id,
                'application_id' => $application->getKey(),
                'recommended_by' => $attributes['recommended_by'] ?? $actor->getKey(),
                'recommended_at' => now(),
            ]);

            $application->forceFill([
                'status' => Application::STATUS_RECOMMENDED,
                'current_stage' => 'recommendation',
            ])->save();

            $this->audit->record('recruitment.application_recommended', $application, after: [
                'recommendation_id' => $recommendation->getKey(),
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $recommendation->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function issueOffer(Application $application, array $attributes, User $actor): JobOffer
    {
        return DB::transaction(function () use ($application, $attributes, $actor): JobOffer {
            $version = (int) JobOffer::withoutTenant()
                ->where('application_id', $application->getKey())
                ->max('offer_version') + 1;

            $offer = JobOffer::create([
                ...$attributes,
                'company_id' => $application->company_id,
                'application_id' => $application->getKey(),
                'offer_version' => $version,
                'status' => JobOffer::STATUS_ISSUED,
                'issued_at' => now(),
            ]);

            $application->forceFill([
                'status' => Application::STATUS_OFFER,
                'current_stage' => 'offer',
            ])->save();

            $this->audit->record('recruitment.offer_issued', $offer, after: $offer->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $offer->refresh();
        });
    }

    public function respondToOffer(JobOffer $offer, string $status, User $actor): JobOffer
    {
        return DB::transaction(function () use ($offer, $status, $actor): JobOffer {
            $offer = JobOffer::withoutTenant()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($status, [JobOffer::STATUS_ACCEPTED, JobOffer::STATUS_DECLINED, JobOffer::STATUS_WITHDRAWN], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Unsupported offer response.',
                ]);
            }

            $offer->forceFill(['status' => $status, 'responded_at' => now()])->save();

            $application = $offer->application;
            $application?->forceFill([
                'status' => $status === JobOffer::STATUS_ACCEPTED
                    ? Application::STATUS_OFFER
                    : Application::STATUS_OFFER_DECLINED,
            ])->save();

            $this->audit->record('recruitment.offer_responded', $offer, after: ['status' => $status], metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $offer->refresh();
        });
    }

    /**
     * Convert an accepted applicant into an employee and open employment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function hire(Application $application, array $attributes, User $actor): Employee
    {
        return DB::transaction(function () use ($application, $attributes, $actor): Employee {
            $application = Application::withoutTenant()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $acceptedOffer = $application->offers()
                ->where('status', JobOffer::STATUS_ACCEPTED)
                ->exists();

            if (! $acceptedOffer && ! ($attributes['override_offer'] ?? false)) {
                throw ValidationException::withMessages([
                    'application_id' => 'The candidate must accept an offer before hire.',
                ]);
            }

            $applicant = $application->applicant;

            if ($applicant === null) {
                throw ValidationException::withMessages([
                    'applicant_id' => 'The application has no applicant profile.',
                ]);
            }

            $existingEmployeeId = $attributes['employee_id'] ?? ($application->metadata['employee_id'] ?? null);

            $existing = $existingEmployeeId === null
                ? null
                : Employee::withoutTenant()
                    ->where('company_id', $application->company_id)
                    ->find($existingEmployeeId);

            $employee = $existing ?? Employee::create([
                'company_id' => $application->company_id,
                'employee_number' => $attributes['employee_number'] ?? $this->nextEmployeeNumber((int) $application->company_id),
                'first_name' => $attributes['first_name'] ?? $this->firstName($applicant->name),
                'last_name' => $attributes['last_name'] ?? $this->lastName($applicant->name),
                'middle_name' => $attributes['middle_name'] ?? null,
                'position' => $application->vacancy?->title,
                'position_id' => $application->vacancy?->position_id,
                'employment_type_id' => $application->vacancy?->employment_type_id,
                'date_hired' => $attributes['start_date'] ?? now()->toDateString(),
                'lifecycle_status' => Employee::LIFECYCLE_ACTIVE,
            ]);

            $employment = $this->employments->start($employee, [
                'employment_type_id' => $application->vacancy?->employment_type_id,
                'classification' => $attributes['classification'] ?? Employment::CLASSIFICATION_REGULAR,
                'start_date' => $attributes['start_date'] ?? now()->toDateString(),
                'end_date' => $attributes['end_date'] ?? null,
                'probation_end' => $attributes['probation_end'] ?? null,
                'contract_reference' => $attributes['contract_reference'] ?? null,
            ], $actor);

            $checklist = OnboardingChecklist::create([
                'company_id' => $application->company_id,
                'employment_id' => $employment->getKey(),
                'employee_id' => $employee->getKey(),
                'name' => 'Onboarding — '.$employee->fullname,
                'status' => 'open',
            ]);

            foreach (['Pre-employment documents', 'Signed employment contract', 'Statutory numbers', 'Payroll setup', 'IT access', 'Equipment acknowledgment', 'Orientation'] as $index => $task) {
                OnboardingTask::create([
                    'company_id' => $application->company_id,
                    'checklist_id' => $checklist->getKey(),
                    'task' => $task,
                    'owner' => $index < 3 ? 'HR' : 'Supervisor',
                    'status' => OnboardingTask::STATUS_PENDING,
                ]);
            }

            $metadata = $application->metadata ?? [];
            $metadata['employee_id'] = $employee->getKey();
            $metadata['employment_id'] = $employment->getKey();

            $application->forceFill([
                'status' => Application::STATUS_HIRED,
                'current_stage' => 'hired',
                'metadata' => $metadata,
            ])->save();

            $this->closeVacancyIfFilled($application);

            $this->audit->record('recruitment.applicant_hired', $application, after: [
                'employee_id' => $employee->getKey(),
                'employment_id' => $employment->getKey(),
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $employee->refresh();
        });
    }

    protected function closeVacancyIfFilled(Application $application): void
    {
        $vacancy = $application->vacancy;

        if ($vacancy === null) {
            return;
        }

        $headcount = $vacancy->requisition?->headcount ?? 1;

        $hired = Application::withoutTenant()
            ->where('job_vacancy_id', $vacancy->getKey())
            ->where('status', Application::STATUS_HIRED)
            ->count();

        if ($hired >= $headcount) {
            $vacancy->forceFill([
                'status' => JobVacancy::STATUS_FILLED,
                'is_active' => false,
            ])->save();
        }
    }

    protected function salaryLabel(JobRequisition $requisition): string
    {
        if ($requisition->salary_min === null && $requisition->salary_max === null) {
            return '';
        }

        return number_format((float) $requisition->salary_min).' – '.number_format((float) $requisition->salary_max);
    }

    protected function nextEmployeeNumber(int $companyId): string
    {
        $next = (int) Employee::withoutTenant()->where('company_id', $companyId)->max('id') + 1;

        return 'EMP-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function firstName(string $name): string
    {
        return trim(explode(' ', trim($name))[0] ?? $name);
    }

    protected function lastName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];

        return (string) array_pop($parts);
    }
}
