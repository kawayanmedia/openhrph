<?php

use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Resources\AttendanceCorrection as AttendanceCorrectionResource;
use App\Livewire\Admin\Resources\DevelopmentPlan as DevelopmentPlanResource;
use App\Livewire\Admin\Resources\EmployeeCertification as EmployeeCertificationResource;
use App\Livewire\Admin\Resources\PerformanceCycle as PerformanceCycleResource;
use App\Livewire\Admin\Resources\PerformanceGoal as PerformanceGoalResource;
use App\Livewire\Admin\Resources\PerformanceRating as PerformanceRatingResource;
use App\Livewire\Admin\Resources\PerformanceReview as PerformanceReviewResource;
use App\Livewire\Admin\Resources\TimesheetApproval as TimesheetApprovalResource;
use App\Livewire\Admin\Resources\TrainingCourse as TrainingCourseResource;
use App\Livewire\Admin\Resources\TrainingEnrollment as TrainingEnrollmentResource;
use App\Livewire\Admin\Resources\TrainingSession as TrainingSessionResource;
use App\Livewire\Admin\Support\ResourceRegistry;
use App\Models\Company;
use App\Models\LearningCourse;
use App\Models\PerformanceCycle;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Workforce Admin Co']);

    $this->admin = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'Workforce Admin',
    ]);

    $this->actingAs($this->admin);

    app(TenantContext::class)->set($this->company->id);

    $this->resources = [
        'attendance-correction' => AttendanceCorrectionResource::class,
        'timesheet-approval' => TimesheetApprovalResource::class,
        'performance-cycle' => PerformanceCycleResource::class,
        'performance-goal' => PerformanceGoalResource::class,
        'performance-review' => PerformanceReviewResource::class,
        'performance-rating' => PerformanceRatingResource::class,
        'development-plan' => DevelopmentPlanResource::class,
        'training-course' => TrainingCourseResource::class,
        'training-session' => TrainingSessionResource::class,
        'training-enrollment' => TrainingEnrollmentResource::class,
        'employee-certification' => EmployeeCertificationResource::class,
    ];
});

it('registers every workforce and talent resource with fields', function (): void {
    app(TenantContext::class)->run($this->company->id, function (): void {
        foreach ($this->resources as $uriKey => $class) {
            expect(ResourceRegistry::find($uriKey))->toBe($class);

            $fields = (new $class)->fields(request());

            expect($fields)->toBeArray()->not->toBeEmpty();
        }
    });
});

it('renders the workforce and talent administration screens', function (): void {
    $creatable = [
        'performance-cycle',
        'performance-goal',
        'development-plan',
        'training-course',
        'training-session',
        'employee-certification',
    ];

    app(TenantContext::class)->run($this->company->id, function () use ($creatable): void {
        foreach (array_keys($this->resources) as $uriKey) {
            Livewire::test(AdminIndex::class, ['resource' => $uriKey])->assertOk();
        }

        foreach ($creatable as $uriKey) {
            Livewire::test(AdminForm::class, ['resource' => $uriKey])->assertOk();
        }
    });
});

it('creates a performance cycle and course through the generic admin form', function (): void {
    app(TenantContext::class)->run($this->company->id, function (): void {
        Livewire::test(AdminForm::class, ['resource' => 'performance-cycle'])
            ->set('form.name', '2027 Annual Review')
            ->set('form.sector', 'Private')
            ->set('form.period_start', '2027-01-01')
            ->set('form.period_end', '2027-12-31')
            ->call('save')
            ->assertHasNoErrors();

        expect(PerformanceCycle::withoutTenant()->where('name', '2027 Annual Review')->exists())->toBeTrue();

        Livewire::test(AdminForm::class, ['resource' => 'training-course'])
            ->set('form.code', 'SAFE-01')
            ->set('form.title', 'Workplace Safety')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        expect(LearningCourse::withoutTenant()->where('code', 'SAFE-01')->exists())->toBeTrue();
    });
});
