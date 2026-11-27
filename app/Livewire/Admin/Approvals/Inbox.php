<?php

namespace App\Livewire\Admin\Approvals;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Inbox extends Component
{
    public ?int $activeTaskId = null;

    public string $comments = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
    }

    public function selectTask(int $taskId): void
    {
        $this->activeTaskId = $taskId;
        $this->comments = '';
    }

    public function clearSelection(): void
    {
        $this->activeTaskId = null;
        $this->comments = '';
    }

    public function approve(WorkflowEngine $engine): void
    {
        $this->act($engine, 'approve');
    }

    public function reject(WorkflowEngine $engine): void
    {
        $this->act($engine, 'reject');
    }

    public function returnForRevision(WorkflowEngine $engine): void
    {
        $this->act($engine, 'return');
    }

    public function cancel(int $instanceId, WorkflowEngine $engine): void
    {
        $instance = WorkflowInstance::query()->findOrFail($instanceId);

        $engine->cancel($instance, $this->user(), $this->comments ?: null);

        session()->flash('admin.saved', 'Workflow cancelled.');
        $this->clearSelection();
    }

    public function render()
    {
        $engine = app(WorkflowEngine::class);

        return view('livewire.admin.approvals.inbox', [
            'tasks' => $engine->pendingTasksFor($this->user()),
            'activeTask' => $this->activeTask(),
        ])->layout('layouts.admin')->title('Approvals');
    }

    protected function act(WorkflowEngine $engine, string $decision): void
    {
        if ($this->comments === '' && in_array($decision, ['reject', 'return'], true)) {
            $this->addError('comments', 'A reason is required when rejecting or returning a request.');

            return;
        }

        $task = WorkflowTask::query()->findOrFail($this->activeTaskId ?? 0);

        match ($decision) {
            'approve' => $engine->approve($task, $this->user(), $this->comments ?: null),
            'reject' => $engine->reject($task, $this->user(), $this->comments),
            'return' => $engine->returnForRevision($task, $this->user(), $this->comments),
        };

        session()->flash('admin.saved', 'Decision recorded.');
        $this->clearSelection();
    }

    protected function activeTask(): ?WorkflowTask
    {
        if ($this->activeTaskId === null) {
            return null;
        }

        return WorkflowTask::query()
            ->with(['instance.subject', 'instance.definition'])
            ->find($this->activeTaskId);
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
