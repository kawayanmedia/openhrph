<?php

namespace App\Notifications\Hr;

use App\Models\WorkflowTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowTaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public WorkflowTask $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Approval needed',
            'message' => $this->task->step_name.' is waiting for your decision.',
            'workflow_task_id' => $this->task->getKey(),
            'workflow_instance_id' => $this->task->workflow_instance_id,
            'url' => route('admin.approvals'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval needed: '.$this->task->step_name)
            ->line('A request is waiting for your decision.')
            ->line('Step: '.$this->task->step_name)
            ->action('Review request', route('admin.approvals'));
    }
}
