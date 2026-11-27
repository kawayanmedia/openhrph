<?php

namespace App\Services\Workflow\Listeners;

use App\Events\Workflow\WorkflowTaskAssigned;
use App\Notifications\Hr\WorkflowTaskAssignedNotification;
use App\Services\Workflow\ApproverResolver;
use Illuminate\Support\Facades\Notification;

class SendWorkflowTaskNotification
{
    public function __construct(private readonly ApproverResolver $resolver) {}

    public function handle(WorkflowTaskAssigned $event): void
    {
        $task = $event->task;

        $recipients = $task->assignedUser !== null
            ? collect([$task->assignedUser])
            : $this->resolver->resolve([
                'type' => $task->assignee_type,
                'value' => $task->assignee_id ?? $task->assignee_label,
            ]);

        $recipients = $recipients->filter()->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new WorkflowTaskAssignedNotification($task));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
