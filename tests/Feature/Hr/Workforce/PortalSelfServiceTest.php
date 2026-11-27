<?php

use App\Livewire\Portal\Documents as PortalDocuments;
use App\Livewire\Portal\Performance as PortalPerformance;
use App\Livewire\Portal\Training as PortalTraining;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Learning\LearningService;
use App\Services\Performance\PerformanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Portal HR Co']);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
    ]);

    $this->user = User::factory()->forCompany($this->company)->create([
        'employee_id' => $this->employee->id,
        'name' => 'Ana Cruz',
        'email' => 'ana.cruz@example.com',
    ]);
});

it('shows the employee performance page and acknowledges a finalized review', function (): void {
    $service = app(PerformanceService::class);

    $cycle = $service->createCycle([
        'company_id' => $this->company->id,
        'name' => '2026 Annual Review',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
    ], $this->user);

    $cycle = $service->activateCycle($cycle, $this->user);

    $review = $service->startReview($cycle, $this->employee, null, $this->user);
    $service->recordRating($review, 'Quality', 4, 100, null, $this->user);
    $service->finalize($review->refresh(), $this->user);

    $this->actingAs($this->user, 'portal')
        ->get('/portal/performance')
        ->assertOk()
        ->assertSee('2026 Annual Review');

    Livewire::actingAs($this->user, 'portal')
        ->test(PortalPerformance::class)
        ->call('acknowledge', $review->id)
        ->assertOk();

    expect(PerformanceReview::withoutTenant()->find($review->id)->acknowledged_at)->not->toBeNull();
});

it('lists the employee training and certification history', function (): void {
    app(LearningService::class)->issueCertification($this->employee, [
        'certification_type' => 'professional',
        'title' => 'Certified Public Accountant',
        'issuer' => 'PRC',
    ], $this->user);

    $this->actingAs($this->user, 'portal')
        ->get('/portal/training')
        ->assertOk()
        ->assertSee('Certified Public Accountant');

    Livewire::actingAs($this->user, 'portal')
        ->test(PortalTraining::class)
        ->assertOk();
});

it('lets the owner acknowledge and download their own document', function (): void {
    $document = Document::create([
        'company_id' => $this->company->id,
        'owner_type' => $this->employee->getMorphClass(),
        'owner_id' => $this->employee->id,
        'title' => 'Notice of Appointment',
        'category' => 'appointment',
        'classification' => Document::CLASSIFICATION_INTERNAL,
        'status' => Document::STATUS_ACTIVE,
        'uploaded_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'portal')
        ->get('/portal/documents')
        ->assertOk()
        ->assertSee('Notice of Appointment');

    Livewire::actingAs($this->user, 'portal')
        ->test(PortalDocuments::class)
        ->call('acknowledge', $document->id)
        ->assertOk();

    expect($document->acknowledgments()->where('user_id', $this->user->id)->exists())->toBeTrue();
});

it('prevents an employee from acknowledging a document owned by someone else', function (): void {
    $otherEmployee = Employee::factory()->create(['company_id' => $this->company->id]);

    $uploader = User::factory()->forCompany($this->company)->create();

    $document = Document::create([
        'company_id' => $this->company->id,
        'owner_type' => $otherEmployee->getMorphClass(),
        'owner_id' => $otherEmployee->id,
        'title' => 'Confidential Memo',
        'category' => 'memo',
        'classification' => Document::CLASSIFICATION_CONFIDENTIAL,
        'status' => Document::STATUS_ACTIVE,
        'uploaded_by' => $uploader->id,
    ]);

    Livewire::actingAs($this->user, 'portal')
        ->test(PortalDocuments::class)
        ->call('acknowledge', $document->id)
        ->assertForbidden();
});
