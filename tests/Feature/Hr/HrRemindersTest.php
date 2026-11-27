<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowTask;
use App\Notifications\Hr\DocumentExpiringNotification;
use App\Notifications\Hr\WorkflowTaskAssignedNotification;
use App\Services\Workflow\WorkflowEngine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function hrReminderDocument(Company $company, array $attributes = []): Document
{
    return app(TenantContext::class)->run($company->id, fn (): Document => Document::create(array_merge([
        'company_id' => $company->id,
        'title' => 'Service Contract',
        'category' => Document::CATEGORY_CONTRACT,
        'classification' => Document::CLASSIFICATION_INTERNAL,
        'status' => Document::STATUS_ACTIVE,
    ], $attributes)));
}

beforeEach(function (): void {
    Notification::fake();

    $this->company = Company::factory()->create(['name' => 'Reminder Co']);
    $this->owner = User::factory()->forCompany($this->company)->create(['name' => 'Owner', 'owner' => true]);

    $this->actingAs($this->owner);
});

it('expires active documents whose expiry date has passed and leaves future ones active', function (): void {
    $overdue = hrReminderDocument($this->company, [
        'title' => 'Overdue Contract',
        'expires_at' => now()->subDays(2)->toDateString(),
    ]);

    $future = hrReminderDocument($this->company, [
        'title' => 'Current Contract',
        'expires_at' => now()->addDays(10)->toDateString(),
    ]);

    $this->artisan('hr:reminders', ['--company' => $this->company->id])->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(Document::STATUS_EXPIRED)
        ->and($future->fresh()->status)->toBe(Document::STATUS_ACTIVE);
});

it('reminds the assigned user about pending workflow tasks due within three days', function (): void {
    $approver = User::factory()->forCompany($this->company)->create(['name' => 'Approver']);
    $document = hrReminderDocument($this->company, ['title' => 'Request']);

    $definition = WorkflowDefinition::create([
        'company_id' => $this->company->id,
        'code' => 'document_review',
        'name' => 'Document review',
        'subject_type' => Document::class,
        'version' => 1,
        'configuration' => [
            'steps' => [[
                'key' => 'review',
                'name' => 'Document review',
                'mode' => WorkflowTask::MODE_ALL,
                'due_days' => 2,
                'assignees' => [['type' => 'user', 'value' => $approver->id]],
            ]],
        ],
        'is_active' => true,
    ]);

    $instance = app(WorkflowEngine::class)->start($definition, $document, $this->owner);

    $task = $instance->tasks()->first();

    expect($task)->not->toBeNull()
        ->and($task->assigned_user_id)->toBe($approver->id)
        ->and($task->due_at->lessThanOrEqualTo(now()->addDays(3)))->toBeTrue();

    // Assigning the task already notifies the approver; start a fresh fake so
    // only notifications produced by the command are recorded.
    Notification::fake();

    $this->artisan('hr:reminders', ['--company' => $this->company->id])->assertSuccessful();

    Notification::assertSentToTimes($approver, WorkflowTaskAssignedNotification::class, 1);
});

it('notifies company owners about documents expiring within thirty days', function (): void {
    hrReminderDocument($this->company, [
        'title' => 'Expiring Contract',
        'expires_at' => now()->addDays(10)->toDateString(),
    ]);

    $this->artisan('hr:reminders', ['--company' => $this->company->id])->assertSuccessful();

    Notification::assertSentTo($this->owner, DocumentExpiringNotification::class);
});
