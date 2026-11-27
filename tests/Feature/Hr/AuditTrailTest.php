<?php

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use App\Services\HrAudit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function hrAuditDocument(Company $company, array $attributes = []): Document
{
    return app(TenantContext::class)->run($company->id, fn (): Document => Document::create(array_merge([
        'company_id' => $company->id,
        'title' => 'Employment Contract',
        'category' => Document::CATEGORY_CONTRACT,
        'classification' => Document::CLASSIFICATION_INTERNAL,
        'status' => Document::STATUS_ACTIVE,
    ], $attributes)));
}

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Audit Co']);
    $this->user = User::factory()->forCompany($this->company)->create(['name' => 'Auditor']);

    $this->actingAs($this->user);
});

it('records the actor, action, subject, before and after values, metadata and occurrence time', function (): void {
    $document = hrAuditDocument($this->company);
    $occurredAt = Carbon::parse('2026-11-20 09:30:00');

    $event = app(AuditLogger::class)->record(
        action: 'document.updated',
        subject: $document,
        before: ['title' => 'Old Contract'],
        after: ['title' => 'Employment Contract'],
        metadata: ['source' => 'test'],
        description: 'Renamed the contract',
        occurredAt: $occurredAt,
    );

    expect($event)->not->toBeNull()
        ->and($event->company_id)->toBe($this->company->id)
        ->and($event->actor_id)->toBe($this->user->id)
        ->and($event->action)->toBe('document.updated')
        ->and($event->subject_type)->toBe($document->getMorphClass())
        ->and($event->subject_id)->toBe($document->id)
        ->and($event->description)->toBe('Renamed the contract')
        ->and($event->before)->toBe(['title' => 'Old Contract'])
        ->and($event->after)->toBe(['title' => 'Employment Contract'])
        ->and($event->metadata)->toBe(['source' => 'test'])
        ->and($event->occurred_at->toDateTimeString())->toBe($occurredAt->toDateTimeString());

    expect(AuditEvent::query()->count())->toBe(1);
});

it('falls back to the tenant context when the subject carries no company', function (): void {
    $subjectWithoutCompany = new Employee;

    $withSubject = app(AuditLogger::class)->record(
        action: 'employee.previewed',
        subject: $subjectWithoutCompany,
    );

    $withoutSubject = app(AuditLogger::class)->record(
        action: 'report.exported',
        subject: null,
    );

    expect($withSubject)->not->toBeNull()
        ->and($withSubject->company_id)->toBe($this->company->id)
        ->and($withSubject->subject_type)->toBe($subjectWithoutCompany->getMorphClass())
        ->and($withSubject->subject_id)->toBeNull()
        ->and($withoutSubject)->not->toBeNull()
        ->and($withoutSubject->company_id)->toBe($this->company->id)
        ->and($withoutSubject->subject_type)->toBeNull();
});

it('keeps audit events immutable', function (): void {
    $event = app(AuditLogger::class)->record(
        action: 'document.created',
        after: ['title' => 'Employment Contract'],
    );

    expect($event)->not->toBeNull();

    expect(fn () => $event->update(['action' => 'document.tampered']))
        ->toThrow(LogicException::class);

    expect(fn () => $event->delete())
        ->toThrow(LogicException::class);

    expect(AuditEvent::query()->whereKey($event->getKey())->value('action'))->toBe('document.created');
});

it('writes an hr-prefixed audit event with the payload through HrAudit', function (): void {
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    HrAudit::record(
        $this->company->id,
        'step_increment',
        $employee->id,
        'Annual step increment',
        ['from_step' => 3, 'to_step' => 4],
    );

    $event = AuditEvent::query()->where('action', 'hr.step_increment')->first();

    expect($event)->not->toBeNull()
        ->and($event->company_id)->toBe($this->company->id)
        ->and($event->actor_id)->toBe($this->user->id)
        ->and($event->subject_type)->toBe($employee->getMorphClass())
        ->and($event->subject_id)->toBe($employee->id)
        ->and($event->after)->toBe(['from_step' => 3, 'to_step' => 4])
        ->and($event->metadata)->toBe([
            'reason' => 'Annual step increment',
            'legacy_event' => 'step_increment',
        ])
        ->and($event->description)->toBe('Annual step increment');

    $this->assertDatabaseHas('hr_audit_events', [
        'company_id' => $this->company->id,
        'employee_id' => $employee->id,
        'event' => 'step_increment',
        'reason' => 'Annual step increment',
    ]);
});

it('does not expose another tenant audit rows through the default scope', function (): void {
    $otherCompany = Company::factory()->create(['name' => 'Other Co']);
    $otherUser = User::factory()->forCompany($otherCompany)->create(['name' => 'Other Auditor']);

    app(TenantContext::class)->run($otherCompany->id, function () use ($otherCompany): void {
        app(AuditLogger::class)->record(
            action: 'document.created',
            after: ['title' => 'Other tenant document'],
            companyId: $otherCompany->id,
        );
    });

    app(TenantContext::class)->run($this->company->id, function (): void {
        app(AuditLogger::class)->record(
            action: 'document.created',
            after: ['title' => 'Own document'],
        );
    });

    $this->actingAs($this->user);

    expect(AuditEvent::query()->count())->toBe(1)
        ->and(AuditEvent::query()->first()->company_id)->toBe($this->company->id)
        ->and(AuditEvent::withoutTenant()->count())->toBe(2)
        ->and(AuditEvent::withoutTenant()->where('company_id', $otherCompany->id)->count())->toBe(1);

    $this->actingAs($otherUser);

    expect(AuditEvent::query()->count())->toBe(1)
        ->and(AuditEvent::query()->first()->company_id)->toBe($otherCompany->id);
});
