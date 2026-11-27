<?php

namespace App\Console\Commands\Hr;

use App\Console\Concerns\RunsForTenants;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Notifications\Hr\DocumentExpiringNotification;
use App\Notifications\Hr\WorkflowTaskAssignedNotification;
use App\Services\Documents\DocumentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class HrReminders extends Command
{
    use RunsForTenants;

    protected $signature = 'hr:reminders {--company= : Only process one company id}';

    protected $description = 'Expire documents and send workflow/document due-date reminders';

    public function handle(DocumentService $documents): int
    {
        $this->forTenantOption(function () use ($documents): void {
            $expired = $documents->expireDueDocuments();
            $taskReminders = $this->remindWorkflowTasks();
            $documentReminders = $this->remindExpiringDocuments();

            $this->line(sprintf(
                'Company %s: %d expired, %d task reminders, %d document reminders.',
                app(TenantContext::class)->id(),
                $expired,
                $taskReminders,
                $documentReminders,
            ));
        });

        return self::SUCCESS;
    }

    protected function remindWorkflowTasks(): int
    {
        $tasks = WorkflowTask::query()
            ->with(['assignedUser', 'instance'])
            ->pending()
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDays(3))
            ->whereHas('instance', fn ($query) => $query->whereIn('status', [
                WorkflowInstance::STATUS_SUBMITTED,
                WorkflowInstance::STATUS_UNDER_REVIEW,
            ]))
            ->get()
            ->filter(fn (WorkflowTask $task) => $task->assignedUser !== null);

        foreach ($tasks as $task) {
            try {
                $task->assignedUser->notify(new WorkflowTaskAssignedNotification($task));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $tasks->count();
    }

    protected function remindExpiringDocuments(): int
    {
        $documentList = Document::query()
            ->where('status', Document::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->get();

        if ($documentList->isEmpty()) {
            return 0;
        }

        $users = User::query()
            ->forTenant((int) app(TenantContext::class)->id())
            ->get()
            ->filter(function (User $user): bool {
                if ($user->owner || $user->ownsAnyCompany()) {
                    return true;
                }

                $permissions = $user->groupForTenant()?->permissions ?? [];

                return ! empty($permissions['Documents']);
            });

        if ($users->isEmpty()) {
            return 0;
        }

        foreach ($documentList as $document) {
            try {
                Notification::send($users, new DocumentExpiringNotification($document));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $documentList->count();
    }
}
