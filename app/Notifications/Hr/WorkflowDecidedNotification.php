<?php

namespace App\Notifications\Hr;

use App\Models\WorkflowInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(public WorkflowInstance $instance, public string $decision) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Workflow '.str_replace('_', ' ', $this->decision),
            'message' => 'The approval workflow is now '.str_replace('_', ' ', $this->decision).'.',
            'workflow_instance_id' => $this->instance->getKey(),
            'decision' => $this->decision,
            'url' => route('admin.approvals'),
        ];
    }
}
