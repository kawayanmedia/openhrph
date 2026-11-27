<?php

namespace App\Services\Workflow\Listeners;

use App\Events\Workflow\WorkflowInstanceDecided;
use App\Notifications\Hr\WorkflowDecidedNotification;

class SendWorkflowDecisionNotification
{
    public function handle(WorkflowInstanceDecided $event): void
    {
        $initiator = $event->instance->initiator;

        if ($initiator === null) {
            return;
        }

        try {
            $initiator->notify(new WorkflowDecidedNotification($event->instance, $event->decision));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
