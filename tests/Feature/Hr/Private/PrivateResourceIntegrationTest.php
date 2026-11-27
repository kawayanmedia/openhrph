<?php

use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Resources\Applicant as ApplicantResource;
use App\Livewire\Admin\Resources\Application as ApplicationResource;
use App\Livewire\Admin\Resources\Assessment as AssessmentResource;
use App\Livewire\Admin\Resources\ClearanceTask as ClearanceTaskResource;
use App\Livewire\Admin\Resources\ClearanceTemplate as ClearanceTemplateResource;
use App\Livewire\Admin\Resources\Employment as EmploymentResource;
use App\Livewire\Admin\Resources\EmploymentContract as EmploymentContractResource;
use App\Livewire\Admin\Resources\FinalSettlement as FinalSettlementResource;
use App\Livewire\Admin\Resources\Interview as InterviewResource;
use App\Livewire\Admin\Resources\InterviewEvaluation as InterviewEvaluationResource;
use App\Livewire\Admin\Resources\JobOffer as JobOfferResource;
use App\Livewire\Admin\Resources\JobRequisition as JobRequisitionResource;
use App\Livewire\Admin\Resources\JobVacancy as JobVacancyResource;
use App\Livewire\Admin\Resources\LeavePolicyRule as LeavePolicyRuleResource;
use App\Livewire\Admin\Resources\OnboardingChecklist as OnboardingChecklistResource;
use App\Livewire\Admin\Resources\OnboardingTask as OnboardingTaskResource;
use App\Livewire\Admin\Resources\ProbationaryReview as ProbationaryReviewResource;
use App\Livewire\Admin\Resources\RegularizationDecision as RegularizationDecisionResource;
use App\Livewire\Admin\Resources\ScreeningResult as ScreeningResultResource;
use App\Livewire\Admin\Resources\SeparationCase as SeparationCaseResource;
use App\Livewire\Admin\Support\ResourceRegistry;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Private Employer']);

    $this->admin = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'Private Admin',
    ]);

    $this->actingAs($this->admin);

    app(TenantContext::class)->set($this->company->id);

    $this->privateResources = [
        'applicant' => ApplicantResource::class,
        'job-requisition' => JobRequisitionResource::class,
        'job-vacancy' => JobVacancyResource::class,
        'application' => ApplicationResource::class,
        'screening-result' => ScreeningResultResource::class,
        'interview' => InterviewResource::class,
        'interview-evaluation' => InterviewEvaluationResource::class,
        'assessment' => AssessmentResource::class,
        'job-offer' => JobOfferResource::class,
        'employment' => EmploymentResource::class,
        'employment-contract' => EmploymentContractResource::class,
        'probationary-review' => ProbationaryReviewResource::class,
        'regularization-decision' => RegularizationDecisionResource::class,
        'onboarding-checklist' => OnboardingChecklistResource::class,
        'onboarding-task' => OnboardingTaskResource::class,
        'leave-policy-rule' => LeavePolicyRuleResource::class,
        'separation-case' => SeparationCaseResource::class,
        'clearance-template' => ClearanceTemplateResource::class,
        'clearance-task' => ClearanceTaskResource::class,
        'final-settlement' => FinalSettlementResource::class,
    ];
});

it('registers every private sector resource and resolves its fields', function () {
    app(TenantContext::class)->run($this->company->id, function (): void {
        foreach ($this->privateResources as $uriKey => $class) {
            expect(ResourceRegistry::find($uriKey))->toBe($class);

            $fields = (new $class)->fields(request());

            expect($fields)->toBeArray()
                ->and($fields)->not->toBeEmpty();
        }
    });
});

it('renders the private sector administration screens', function () {
    $indexable = [
        'job-requisition',
        'applicant',
        'application',
        'employment',
        'separation-case',
        'leave-policy-rule',
    ];

    $creatable = [
        'job-requisition',
        'applicant',
        'application',
        'leave-policy-rule',
    ];

    app(TenantContext::class)->run($this->company->id, function () use ($indexable, $creatable): void {
        foreach ($indexable as $uriKey) {
            Livewire::test(AdminIndex::class, ['resource' => $uriKey])->assertOk();
        }

        foreach ($creatable as $uriKey) {
            Livewire::test(AdminForm::class, ['resource' => $uriKey])->assertOk();
        }
    });
});
