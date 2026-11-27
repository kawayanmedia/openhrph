<?php

use App\Livewire\Admin\Approvals\Inbox;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['mail.default' => 'array']);

    $this->company = Company::factory()->create(['name' => 'Acme']);
    $this->user = User::factory()->forCompany($this->company)->create(['owner' => true, 'name' => 'Approver']);
    $this->initiator = User::factory()->forCompany($this->company)->create(['name' => 'Initiator']);

    $this->actingAs($this->user);

    $this->definition = WorkflowDefinition::create([
        'company_id' => $this->company->id,
        'code' => 'document_approval',
        'name' => 'Document approval',
        'version' => 1,
        'configuration' => [
            'steps' => [
                [
                    'key' => 'review',
                    'name' => 'Review',
                    'mode' => WorkflowTask::MODE_ALL,
                    'due_days' => 2,
                    'assignees' => [['type' => 'user', 'value' => $this->user->id]],
                ],
            ],
        ],
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->document = Document::create([
        'company_id' => $this->company->id,
        'title' => 'Request',
        'category' => Document::CATEGORY_OTHER,
        'classification' => Document::CLASSIFICATION_INTERNAL,
    ]);

    $this->instance = app(WorkflowEngine::class)->start($this->definition, $this->document, $this->initiator);
    $this->task = $this->instance->tasks()->firstOrFail();
});

it('shows a pending task and approves it from the inbox', function () {
    Livewire::test(Inbox::class)
        ->assertSee('Review')
        ->call('selectTask', $this->task->id)
        ->set('comments', 'ok')
        ->call('approve')
        ->assertHasNoErrors();

    expect($this->task->refresh()->status)->toBe(WorkflowTask::STATUS_APPROVED)
        ->and($this->task->acted_by)->toBe($this->user->id)
        ->and($this->instance->refresh()->status)->toBe(WorkflowInstance::STATUS_APPROVED);
});

it('requires a reason before rejecting a task', function () {
    Livewire::test(Inbox::class)
        ->call('selectTask', $this->task->id)
        ->set('comments', '')
        ->call('reject')
        ->assertHasErrors(['comments']);

    expect($this->task->refresh()->status)->toBe(WorkflowTask::STATUS_PENDING)
        ->and($this->instance->refresh()->status)->toBe(WorkflowInstance::STATUS_UNDER_REVIEW)
        ->and($this->instance->decisions()->count())->toBe(0);
});
