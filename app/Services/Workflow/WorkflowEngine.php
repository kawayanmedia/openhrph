<?php

namespace App\Services\Workflow;

use App\Events\Workflow\WorkflowInstanceDecided;
use App\Events\Workflow\WorkflowTaskAssigned;
use App\Models\User;
use App\Models\WorkflowDecision;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowDelegation;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Services\Hr\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Declarative approval engine.
 *
 * Domain rules stay in the domain: a leave balance or an appointment
 * eligibility check is never delegated to a workflow definition.
 */
class WorkflowEngine
{
    public function __construct(
        private readonly WorkflowDefinitionValidator $validator,
        private readonly ApproverResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function definitionFor(string $code, ?int $companyId = null, ?string $subjectType = null): WorkflowDefinition
    {
        $companyId ??= app(TenantContext::class)->id();

        $definition = WorkflowDefinition::withoutTenant()
            ->active()
            ->where('code', $code)
            ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
            ->when($subjectType !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('subject_type')->orWhere('subject_type', $subjectType)))
            ->orderByRaw('company_id is null')
            ->orderByDesc('version')
            ->first();

        if ($definition === null) {
            throw ValidationException::withMessages([
                'workflow' => "No active workflow definition exists for '{$code}'.",
            ]);
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function start(
        WorkflowDefinition|string $definition,
        Model $subject,
        ?User $initiator = null,
        array $metadata = [],
    ): WorkflowInstance {
        $definition = is_string($definition)
            ? $this->definitionFor($definition, (int) $subject->getAttribute('company_id'), $subject->getMorphClass())
            : $definition;

        $steps = $this->validator->normalize($definition->configuration);

        return DB::transaction(function () use ($definition, $steps, $subject, $initiator, $metadata): WorkflowInstance {
            $first = $steps[0];

            $instance = WorkflowInstance::create([
                'company_id' => $subject->getAttribute('company_id') ?? app(TenantContext::class)->id(),
                'workflow_definition_id' => $definition->getKey(),
                'definition_version' => $definition->version,
                'definition_snapshot' => ['steps' => $steps],
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'status' => WorkflowInstance::STATUS_UNDER_REVIEW,
                'current_step' => $first['key'],
                'initiated_by' => $initiator?->getKey(),
                'submitted_at' => now(),
                'due_at' => $this->dueAt($first),
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            $this->createTasks($instance, $first);

            $this->audit->record(
                'workflow.instance_started',
                $instance,
                after: ['status' => $instance->status, 'step' => $first['key']],
                metadata: ['definition' => $definition->code, 'version' => $definition->version],
            );

            return $instance->refresh();
        });
    }

    public function approve(WorkflowTask $task, User $actor, ?string $comments = null): WorkflowInstance
    {
        return DB::transaction(function () use ($task, $actor, $comments): WorkflowInstance {
            [$task, $instance] = $this->lockedTask($task);

            $this->guardActionable($task, $instance, $actor);

            $task->forceFill([
                'status' => WorkflowTask::STATUS_APPROVED,
                'acted_at' => now(),
                'acted_by' => $actor->getKey(),
            ])->save();

            $this->decide($instance, $task, $actor, WorkflowDecision::DECISION_APPROVE, $comments);

            if ($task->mode === WorkflowTask::MODE_ANY) {
                $this->cancelStepSiblings($instance, $task);
            }

            $this->advanceIfComplete($instance, $task->step_order);

            $this->audit->record(
                'workflow.task_approved',
                $instance,
                after: ['task_id' => $task->getKey(), 'step' => $task->step_key],
                metadata: ['actor_id' => $actor->getKey(), 'comments' => $comments],
            );

            return $instance->refresh();
        });
    }

    public function reject(WorkflowTask $task, User $actor, string $comments): WorkflowInstance
    {
        return DB::transaction(function () use ($task, $actor, $comments): WorkflowInstance {
            [$task, $instance] = $this->lockedTask($task);

            $this->guardActionable($task, $instance, $actor);

            $task->forceFill([
                'status' => WorkflowTask::STATUS_REJECTED,
                'acted_at' => now(),
                'acted_by' => $actor->getKey(),
            ])->save();

            $instance->forceFill([
                'status' => WorkflowInstance::STATUS_REJECTED,
                'completed_at' => now(),
            ])->save();

            $this->cancelPendingTasks($instance);

            $this->decide($instance, $task, $actor, WorkflowDecision::DECISION_REJECT, $comments);

            $this->audit->record(
                'workflow.instance_rejected',
                $instance,
                after: ['status' => $instance->status],
                metadata: ['actor_id' => $actor->getKey(), 'comments' => $comments],
            );

            WorkflowInstanceDecided::dispatch($instance, $actor, WorkflowInstance::STATUS_REJECTED);

            return $instance->refresh();
        });
    }

    public function returnForRevision(WorkflowTask $task, User $actor, string $comments): WorkflowInstance
    {
        return DB::transaction(function () use ($task, $actor, $comments): WorkflowInstance {
            [$task, $instance] = $this->lockedTask($task);

            $this->guardActionable($task, $instance, $actor);

            $task->forceFill([
                'status' => WorkflowTask::STATUS_RETURNED,
                'acted_at' => now(),
                'acted_by' => $actor->getKey(),
            ])->save();

            $instance->forceFill(['status' => WorkflowInstance::STATUS_RETURNED])->save();

            $this->cancelPendingTasks($instance);

            $this->decide($instance, $task, $actor, WorkflowDecision::DECISION_RETURN, $comments);

            $this->audit->record(
                'workflow.instance_returned',
                $instance,
                after: ['status' => $instance->status],
                metadata: ['actor_id' => $actor->getKey(), 'comments' => $comments],
            );

            WorkflowInstanceDecided::dispatch($instance, $actor, WorkflowInstance::STATUS_RETURNED);

            return $instance->refresh();
        });
    }

    public function resubmit(WorkflowInstance $instance, ?User $actor = null): WorkflowInstance
    {
        return DB::transaction(function () use ($instance): WorkflowInstance {
            $instance = WorkflowInstance::withoutTenant()->whereKey($instance->getKey())->lockForUpdate()->firstOrFail();

            if ($instance->status !== WorkflowInstance::STATUS_RETURNED) {
                throw ValidationException::withMessages([
                    'workflow' => 'Only a returned workflow can be resubmitted.',
                ]);
            }

            $steps = $this->validator->normalize($instance->definition_snapshot);
            $first = $steps[0];

            $instance->forceFill([
                'status' => WorkflowInstance::STATUS_UNDER_REVIEW,
                'current_step' => $first['key'],
                'due_at' => $this->dueAt($first),
                'completed_at' => null,
            ])->save();

            $this->createTasks($instance, $first);

            $this->audit->record('workflow.instance_resubmitted', $instance, after: ['step' => $first['key']]);

            return $instance->refresh();
        });
    }

    public function cancel(WorkflowInstance $instance, User $actor, ?string $reason = null): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $actor, $reason): WorkflowInstance {
            $instance = WorkflowInstance::withoutTenant()->whereKey($instance->getKey())->lockForUpdate()->firstOrFail();

            if (! $instance->isOpen()) {
                throw ValidationException::withMessages([
                    'workflow' => 'This workflow is already closed.',
                ]);
            }

            if ((int) $instance->initiated_by !== (int) $actor->getKey() && ! $actor->can(config('admin.gate'))) {
                throw new AuthorizationException('Only the initiator or an administrator may cancel this workflow.');
            }

            $instance->forceFill([
                'status' => WorkflowInstance::STATUS_CANCELLED,
                'completed_at' => now(),
            ])->save();

            $this->cancelPendingTasks($instance);

            WorkflowDecision::create([
                'company_id' => $instance->company_id,
                'workflow_instance_id' => $instance->getKey(),
                'actor_id' => $actor->getKey(),
                'decision' => WorkflowDecision::DECISION_CANCEL,
                'comments' => $reason,
                'decided_at' => now(),
            ]);

            $this->audit->record('workflow.instance_cancelled', $instance, metadata: ['actor_id' => $actor->getKey(), 'reason' => $reason]);

            return $instance->refresh();
        });
    }

    public function delegate(
        WorkflowTask $task,
        User $actor,
        User $delegatee,
        Carbon $startsAt,
        Carbon $endsAt,
        ?string $reason = null,
    ): WorkflowDelegation {
        return DB::transaction(function () use ($task, $actor, $delegatee, $startsAt, $endsAt, $reason): WorkflowDelegation {
            [$task, $instance] = $this->lockedTask($task);

            if (! $this->resolver->canAct($task, $actor)) {
                throw new AuthorizationException('You are not assigned to this task.');
            }

            if ($endsAt->lt($startsAt)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'The delegation end date must be on or after the start date.',
                ]);
            }

            $delegation = WorkflowDelegation::create([
                'company_id' => $instance->company_id,
                'delegator_id' => $task->assigned_user_id ?? $actor->getKey(),
                'delegatee_id' => $delegatee->getKey(),
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'reason' => $reason,
                'is_active' => true,
            ]);

            $task->forceFill(['assigned_user_id' => $delegatee->getKey()])->save();

            $this->decide($instance, $task, $actor, WorkflowDecision::DECISION_DELEGATE, $reason, $delegatee);

            $this->audit->record(
                'workflow.task_delegated',
                $instance,
                after: ['task_id' => $task->getKey(), 'delegatee_id' => $delegatee->getKey()],
                metadata: ['actor_id' => $actor->getKey(), 'reason' => $reason],
            );

            WorkflowTaskAssigned::dispatch($task->refresh());

            return $delegation;
        });
    }

    /**
     * Pending tasks the user can act on, directly or through delegation.
     *
     * @return Collection<int, WorkflowTask>
     */
    public function pendingTasksFor(User $user)
    {
        $delegatedFrom = WorkflowDelegation::withoutTenant()
            ->where('delegatee_id', $user->getKey())
            ->current()
            ->pluck('delegator_id')
            ->all();

        return WorkflowTask::withoutTenant()
            ->with(['instance', 'instance.definition', 'assignedUser'])
            ->pending()
            ->whereHas('instance', fn ($query) => $query->whereIn('status', [
                WorkflowInstance::STATUS_SUBMITTED,
                WorkflowInstance::STATUS_UNDER_REVIEW,
            ]))
            ->orderBy('due_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (WorkflowTask $task) => $this->resolver->canAct($task, $user)
                || ($task->assigned_user_id !== null && in_array((int) $task->assigned_user_id, $delegatedFrom, true)))
            ->values();
    }

    /**
     * @return array{0: WorkflowTask, 1: WorkflowInstance}
     */
    protected function lockedTask(WorkflowTask $task): array
    {
        $task = WorkflowTask::withoutTenant()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
        $instance = WorkflowInstance::withoutTenant()
            ->whereKey($task->workflow_instance_id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$task, $instance];
    }

    protected function guardActionable(WorkflowTask $task, WorkflowInstance $instance, User $actor): void
    {
        if (! in_array($instance->status, [WorkflowInstance::STATUS_SUBMITTED, WorkflowInstance::STATUS_UNDER_REVIEW], true)) {
            throw ValidationException::withMessages([
                'workflow' => 'This workflow is not open for approval.',
            ]);
        }

        if (! $task->isPending()) {
            throw ValidationException::withMessages([
                'workflow' => 'This approval task has already been decided.',
            ]);
        }

        if (! $this->resolver->canAct($task, $actor)) {
            throw new AuthorizationException('You are not assigned to this approval task.');
        }
    }

    protected function advanceIfComplete(WorkflowInstance $instance, int $stepOrder): void
    {
        $stepTasks = $instance->tasks()->where('step_order', $stepOrder)->get();
        $mode = $stepTasks->first()?->mode ?? WorkflowTask::MODE_ALL;

        if ($mode === WorkflowTask::MODE_ALL && $stepTasks->contains(fn (WorkflowTask $task) => $task->status === WorkflowTask::STATUS_PENDING)) {
            return;
        }

        $steps = $this->validator->normalize($instance->definition_snapshot);
        $next = collect($steps)->first(fn (array $step) => $step['order'] > $stepOrder);

        if ($next === null) {
            $instance->forceFill([
                'status' => WorkflowInstance::STATUS_APPROVED,
                'completed_at' => now(),
                'current_step' => null,
            ])->save();

            WorkflowInstanceDecided::dispatch($instance, null, WorkflowInstance::STATUS_APPROVED);

            return;
        }

        $instance->forceFill([
            'current_step' => $next['key'],
            'due_at' => $this->dueAt($next),
        ])->save();

        $this->createTasks($instance, $next);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function createTasks(WorkflowInstance $instance, array $step): void
    {
        $created = 0;

        foreach ($step['assignees'] as $assignee) {
            $users = $this->resolver->resolve($assignee);
            $label = $assignee['label'] ?? (string) $assignee['value'];

            if ($users->isEmpty()) {
                WorkflowTask::create([
                    'company_id' => $instance->company_id,
                    'workflow_instance_id' => $instance->getKey(),
                    'step_key' => $step['key'],
                    'step_order' => $step['order'],
                    'step_name' => $step['name'],
                    'mode' => $step['mode'],
                    'assignee_type' => $assignee['type'],
                    'assignee_id' => is_int($assignee['value']) ? $assignee['value'] : null,
                    'assignee_label' => $label,
                    'assigned_user_id' => null,
                    'status' => WorkflowTask::STATUS_PENDING,
                    'due_at' => $this->dueAt($step),
                ]);

                $created++;

                continue;
            }

            foreach ($users as $user) {
                $task = WorkflowTask::create([
                    'company_id' => $instance->company_id,
                    'workflow_instance_id' => $instance->getKey(),
                    'step_key' => $step['key'],
                    'step_order' => $step['order'],
                    'step_name' => $step['name'],
                    'mode' => $step['mode'],
                    'assignee_type' => $assignee['type'],
                    'assignee_id' => is_int($assignee['value']) ? $assignee['value'] : null,
                    'assignee_label' => $label,
                    'assigned_user_id' => $user->getKey(),
                    'status' => WorkflowTask::STATUS_PENDING,
                    'due_at' => $this->dueAt($step),
                ]);

                WorkflowTaskAssigned::dispatch($task);

                $created++;
            }
        }

        if ($created === 0) {
            throw ValidationException::withMessages([
                'workflow' => 'No approvers could be resolved for the next workflow step.',
            ]);
        }
    }

    protected function cancelStepSiblings(WorkflowInstance $instance, WorkflowTask $task): void
    {
        WorkflowTask::withoutTenant()
            ->where('workflow_instance_id', $instance->getKey())
            ->where('step_order', $task->step_order)
            ->whereKeyNot($task->getKey())
            ->pending()
            ->update(['status' => WorkflowTask::STATUS_CANCELLED]);
    }

    protected function cancelPendingTasks(WorkflowInstance $instance): void
    {
        WorkflowTask::withoutTenant()
            ->where('workflow_instance_id', $instance->getKey())
            ->pending()
            ->update(['status' => WorkflowTask::STATUS_CANCELLED]);
    }

    protected function decide(
        WorkflowInstance $instance,
        WorkflowTask $task,
        User $actor,
        string $decision,
        ?string $comments = null,
        ?User $delegatee = null,
    ): WorkflowDecision {
        return WorkflowDecision::create([
            'company_id' => $instance->company_id,
            'workflow_instance_id' => $instance->getKey(),
            'workflow_task_id' => $task->getKey(),
            'actor_id' => $actor->getKey(),
            'decision' => $decision,
            'comments' => $comments,
            'delegated_to_id' => $delegatee?->getKey(),
            'decided_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function dueAt(array $step): ?Carbon
    {
        return $step['due_days'] === null
            ? null
            : now()->addDays((int) $step['due_days']);
    }
}
