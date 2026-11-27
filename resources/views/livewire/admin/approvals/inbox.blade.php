<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Approvals</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Requests assigned to you directly, through your group, your organizational unit, or a delegation.
        </p>
    </div>

    @if (session('admin.saved'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('admin.saved') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @forelse ($tasks as $task)
                <button type="button"
                        wire:key="task-{{ $task->id }}"
                        wire:click="selectTask({{ $task->id }})"
                        class="block w-full rounded-xl border bg-white p-4 text-left transition hover:border-teal-400 dark:bg-navy-900 {{ $activeTaskId === $task->id ? 'border-teal-500 ring-1 ring-teal-500' : 'border-slate-200 dark:border-navy-700' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-navy-900 dark:text-white">{{ $task->step_name }}</p>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-navy-800 dark:text-slate-300">
                            {{ $task->instance?->definition?->name ?? 'Workflow' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ class_basename($task->instance?->subject_type ?? '') }} #{{ $task->instance?->subject_id }}
                        · {{ $task->assignee_type }}: {{ $task->assignee_label }}
                    </p>
                    <p class="mt-1 text-xs {{ $task->isOverdue() ? 'font-semibold text-red-600 dark:text-red-400' : 'text-slate-400' }}">
                        @if ($task->due_at)
                            Due {{ $task->due_at->toFormattedDateString() }}{{ $task->isOverdue() ? ' (overdue)' : '' }}
                        @else
                            No due date
                        @endif
                    </p>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-navy-700 dark:text-slate-400">
                    Nothing is waiting for your decision.
                </div>
            @endforelse
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-navy-700 dark:bg-navy-900">
            @if ($activeTask)
                <h2 class="text-base font-semibold text-navy-900 dark:text-white">Decision</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $activeTask->step_name }} for {{ class_basename($activeTask->instance?->subject_type ?? '') }} #{{ $activeTask->instance?->subject_id }}
                </p>

                <label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-200" for="comments">Comments</label>
                <textarea id="comments" rows="4" wire:model="comments"
                          class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-navy-700 dark:bg-navy-950 dark:text-white"
                          placeholder="Reason for rejection, return, or notes"></textarea>
                @error('comments')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" wire:click="approve" class="ui-btn-primary">Approve</button>
                    <button type="button" wire:click="returnForRevision" class="ui-btn-secondary">Return</button>
                    <button type="button" wire:click="reject" class="ui-btn-secondary !text-red-600">Reject</button>
                    <button type="button" wire:click="clearSelection" class="ui-btn-secondary">Cancel</button>
                </div>
            @else
                <h2 class="text-base font-semibold text-navy-900 dark:text-white">Select a request</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Choose a pending approval to approve, return for revision, or reject it.
                </p>
            @endif
        </div>
    </div>
</div>
