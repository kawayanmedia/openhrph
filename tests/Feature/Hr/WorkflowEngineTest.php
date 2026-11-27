<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowDecision;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Services\Workflow\WorkflowEngine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $steps
 */
function workflowEngineDefinition(Company $company, User $creator, array $steps, string $code = 'document_approval'): WorkflowDefinition
{
    return WorkflowDefinition::create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => 'Document approval',
        'version' => 1,
        'configuration' => ['steps' => $steps],
        'is_active' => true,
        'created_by' => $creator->id,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $assignees
 * @return array<string, mixed>
 */
function workflowEngineStep(string $key, array $assignees, string $mode = WorkflowTask::MODE_ALL): array
{
    return [
        'key' => $key,
        'name' => ucfirst($key),
        'mode' => $mode,
        'due_days' => 2,
        'assignees' => $assignees,
    ];
}

/**
 * @return array{type: string, value: int}
 */
function workflowEngineUser(User $user): array
{
    return ['type' => 'user', 'value' => $user->id];
}

function workflowEngineDocument(Company $company): Document
{
    return app(TenantContext::class)->run($company->id, fn (): Document => Document::create([
        'company_id' => $company->id,
        'title' => 'Request',
        'category' => Document::CATEGORY_OTHER,
        'classification' => Document::CLASSIFICATION_INTERNAL,
    ]));
}

beforeEach(function () {
    config(['mail.default' => 'array']);

    $this->company = Company::factory()->create(['name' => 'Acme']);
    $this->approver = User::factory()->forCompany($this->company)->create(['name' => 'Approver']);
    $this->second = User::factory()->forCompany($this->company)->create(['name' => 'Second']);
    $this->outsider = User::factory()->forCompany($this->company)->create(['name' => 'Outsider']);
    $this->initiator = User::factory()->forCompany($this->company)->create(['name' => 'Initiator']);

    $this->actingAs($this->approver);

    $this->engine = app(WorkflowEngine::class);
});

it('runs a sequential workflow one step at a time', function () {
    $definition = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [workflowEngineUser($this->approver)]),
        workflowEngineStep('endorse', [workflowEngineUser($this->second)]),
    ]);

    $instance = $this->engine->start($definition, workflowEngineDocument($this->company), $this->initiator);

    $firstTasks = $instance->tasks()->get();

    expect($firstTasks)->toHaveCount(1)
        ->and($firstTasks->first()->step_key)->toBe('review')
        ->and($firstTasks->first()->assigned_user_id)->toBe($this->approver->id)
        ->and($firstTasks->first()->status)->toBe(WorkflowTask::STATUS_PENDING)
        ->and($instance->status)->toBe(WorkflowInstance::STATUS_UNDER_REVIEW);

    $this->engine->approve($firstTasks->first(), $this->approver, 'step one ok');

    expect($instance->refresh()->current_step)->toBe('endorse');

    $secondTasks = $instance->tasks()->where('status', WorkflowTask::STATUS_PENDING)->get();

    expect($secondTasks)->toHaveCount(1)
        ->and($secondTasks->first()->step_key)->toBe('endorse')
        ->and($secondTasks->first()->assigned_user_id)->toBe($this->second->id);

    $this->engine->approve($secondTasks->first(), $this->second, 'step two ok');

    $instance->refresh();

    expect($instance->status)->toBe(WorkflowInstance::STATUS_APPROVED)
        ->and($instance->completed_at)->not->toBeNull()
        ->and($instance->current_step)->toBeNull()
        ->and($instance->decisions()->count())->toBe(2);
});

it('cancels sibling tasks when an any-mode step is approved', function () {
    $definition = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [
            workflowEngineUser($this->approver),
            workflowEngineUser($this->second),
        ], WorkflowTask::MODE_ANY),
    ]);

    $instance = $this->engine->start($definition, workflowEngineDocument($this->company), $this->initiator);

    $tasks = $instance->tasks()->get();

    expect($tasks)->toHaveCount(2)
        ->and($tasks->pluck('status')->unique()->all())->toBe([WorkflowTask::STATUS_PENDING]);

    $approved = $tasks->first();
    $sibling = $tasks->last();

    $this->engine->approve($approved, $this->approver, 'approved by one');

    expect($approved->refresh()->status)->toBe(WorkflowTask::STATUS_APPROVED)
        ->and($sibling->refresh()->status)->toBe(WorkflowTask::STATUS_CANCELLED)
        ->and($instance->refresh()->status)->toBe(WorkflowInstance::STATUS_APPROVED)
        ->and($instance->decisions()->count())->toBe(1);
});

it('rejects a workflow and cancels the remaining tasks', function () {
    $definition = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [
            workflowEngineUser($this->approver),
            workflowEngineUser($this->second),
        ]),
    ]);

    $instance = $this->engine->start($definition, workflowEngineDocument($this->company), $this->initiator);

    $task = $instance->tasks()->where('assigned_user_id', $this->approver->id)->firstOrFail();
    $other = $instance->tasks()->where('assigned_user_id', $this->second->id)->firstOrFail();

    $this->engine->reject($task, $this->approver, 'Missing supporting document');

    $instance->refresh();

    expect($instance->status)->toBe(WorkflowInstance::STATUS_REJECTED)
        ->and($instance->completed_at)->not->toBeNull()
        ->and($task->refresh()->status)->toBe(WorkflowTask::STATUS_REJECTED)
        ->and($other->refresh()->status)->toBe(WorkflowTask::STATUS_CANCELLED)
        ->and($instance->decisions()->where('decision', WorkflowDecision::DECISION_REJECT)->count())->toBe(1);
});

it('returns a workflow for revision and creates a fresh task on resubmit', function () {
    $definition = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [workflowEngineUser($this->approver)]),
    ]);

    $instance = $this->engine->start($definition, workflowEngineDocument($this->company), $this->initiator);

    $task = $instance->tasks()->firstOrFail();

    $this->engine->returnForRevision($task, $this->approver, 'Please attach the signed copy');

    expect($instance->refresh()->status)->toBe(WorkflowInstance::STATUS_RETURNED)
        ->and($task->refresh()->status)->toBe(WorkflowTask::STATUS_RETURNED)
        ->and($instance->tasks()->where('status', WorkflowTask::STATUS_PENDING)->count())->toBe(0);

    $resubmitted = $this->engine->resubmit($instance, $this->initiator);

    $pending = $resubmitted->tasks()->where('status', WorkflowTask::STATUS_PENDING)->get();

    expect($resubmitted->status)->toBe(WorkflowInstance::STATUS_UNDER_REVIEW)
        ->and($resubmitted->current_step)->toBe('review')
        ->and($resubmitted->completed_at)->toBeNull()
        ->and($pending)->toHaveCount(1)
        ->and($pending->first()->step_key)->toBe('review')
        ->and($pending->first()->assigned_user_id)->toBe($this->approver->id);
});

it('delegates a task and lets the delegatee decide it', function () {
    $definition = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [workflowEngineUser($this->approver)]),
    ]);

    $instance = $this->engine->start($definition, workflowEngineDocument($this->company), $this->initiator);

    $task = $instance->tasks()->firstOrFail();

    $delegation = $this->engine->delegate(
        $task,
        $this->approver,
        $this->outsider,
        Carbon::now(),
        Carbon::now()->addDays(5),
        'Out of office',
    );

    expect($delegation->delegator_id)->toBe($this->approver->id)
        ->and($delegation->delegatee_id)->toBe($this->outsider->id)
        ->and($delegation->is_active)->toBeTrue()
        ->and($task->refresh()->assigned_user_id)->toBe($this->outsider->id)
        ->and($task->status)->toBe(WorkflowTask::STATUS_PENDING);

    $delegationDecision = $instance->decisions()
        ->where('decision', WorkflowDecision::DECISION_DELEGATE)
        ->firstOrFail();

    expect($delegationDecision->actor_id)->toBe($this->approver->id)
        ->and($delegationDecision->delegated_to_id)->toBe($this->outsider->id)
        ->and($delegationDecision->workflow_task_id)->toBe($task->id);

    $this->engine->approve($task, $this->outsider, 'Handled by delegate');

    expect($instance->refresh()->status)->toBe(WorkflowInstance::STATUS_APPROVED)
        ->and($task->refresh()->acted_by)->toBe($this->outsider->id);
});

it('refuses an approval from a user who is not assigned', function () {
    $definition = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [workflowEngineUser($this->approver)]),
    ]);

    $instance = $this->engine->start($definition, workflowEngineDocument($this->company), $this->initiator);

    $task = $instance->tasks()->firstOrFail();

    expect(fn () => $this->engine->approve($task, $this->outsider, 'not mine'))
        ->toThrow(AuthorizationException::class);

    expect($task->refresh()->status)->toBe(WorkflowTask::STATUS_PENDING)
        ->and($instance->refresh()->status)->toBe(WorkflowInstance::STATUS_UNDER_REVIEW);
});

it('keeps decisions immutable', function () {
    $definition = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [workflowEngineUser($this->approver)]),
    ]);

    $instance = $this->engine->start($definition, workflowEngineDocument($this->company), $this->initiator);

    $this->engine->approve($instance->tasks()->firstOrFail(), $this->approver, 'looks fine');

    $decision = $instance->decisions()->firstOrFail();

    expect(fn () => $decision->update(['comments' => 'rewritten']))
        ->toThrow(LogicException::class);

    expect(fn () => $decision->delete())
        ->toThrow(LogicException::class);

    expect($decision->refresh()->comments)->toBe('looks fine');
});

it('does not expose another tenant tasks in the inbox query', function () {
    $definitionA = workflowEngineDefinition($this->company, $this->approver, [
        workflowEngineStep('review', [workflowEngineUser($this->approver)]),
    ]);

    $taskA = $this->engine->start($definitionA, workflowEngineDocument($this->company), $this->initiator)
        ->tasks()
        ->firstOrFail();

    $otherCompany = Company::factory()->create(['name' => 'Other']);
    $otherUser = User::factory()->forCompany($otherCompany)->create();

    $taskB = app(TenantContext::class)->run($otherCompany->id, function () use ($otherCompany, $otherUser): WorkflowTask {
        $definition = workflowEngineDefinition($otherCompany, $otherUser, [
            workflowEngineStep('review', [workflowEngineUser($otherUser)]),
        ], 'other_approval');

        return $this->engine
            ->start($definition, workflowEngineDocument($otherCompany), $otherUser)
            ->tasks()
            ->firstOrFail();
    });

    $pending = $this->engine->pendingTasksFor($this->approver);

    expect($pending->pluck('id')->all())->toBe([$taskA->id])
        ->and($pending->pluck('id')->all())->not->toContain($taskB->id);

    $otherPending = app(TenantContext::class)->run(
        $otherCompany->id,
        fn () => $this->engine->pendingTasksFor($otherUser),
    );

    expect($otherPending->pluck('id')->all())->toBe([$taskB->id]);
});
