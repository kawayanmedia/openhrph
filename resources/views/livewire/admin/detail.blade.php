<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-teal-700 dark:text-slate-400 dark:hover:text-teal-400">&larr; {{ $this->resource::pluralLabel() }}</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">{{ $this->resource->title() }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $this->resource::label() }} #{{ $model->getKey() }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($canUpdate)
                <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey . '/' . $model->getKey() . '/edit') }}"
                   class="ui-btn-primary">Edit</a>
            @endif

            @foreach ($actions as $action)
                <button wire:click="openDetailAction('{{ class_basename($action) }}')"
                        class="ui-btn-secondary">
                    {{ $action->name() }}
                </button>
            @endforeach

            @if ($canDelete)
                <button wire:click="confirmDelete"
                        class="ui-btn-danger">
                    Delete
                </button>
            @endif
        </div>
    </div>

    <div class="ui-card overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-navy-800">
            <h2 class="text-base font-semibold text-navy-900 dark:text-white">{{ $this->resource::label() }} Details</h2>
        </div>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($fields as $field)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $field->name }}</dt>
                    <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                        @include('livewire.admin.partials.field-display', ['field' => $field, 'model' => $model])
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    @foreach ($relationFields as $relationField)
        <div class="ui-card mt-6 overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-navy-800">
                <h2 class="text-base font-semibold text-navy-900 dark:text-white">{{ $relationField->name }}</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ is_countable($model->{$relationField->attribute}) ? count($model->{$relationField->attribute}) : 0 }} records</span>
            </div>
            <div class="px-6 py-4">
                @php($related = $model->{$relationField->attribute})
                @if ($related && count($related))
                    <ul class="divide-y divide-slate-100 dark:divide-navy-800">
                        @foreach ($related as $item)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span class="text-slate-700 dark:text-slate-200">{{ $item->name ?? $item->title ?? $item->fullname ?? $item->getKey() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">No related records.</p>
                @endif
            </div>
        </div>
    @endforeach

    @if ($showActionModal && $activeAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4 backdrop-blur-sm" wire:keydown.escape="closeAction">
            <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl dark:bg-navy-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-navy-800">
                    <h3 class="text-lg font-semibold text-navy-900 dark:text-white">{{ $activeAction->name() }}</h3>
                </div>

                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    @forelse ($actionFieldList as $actionField)
                        <div class="mb-4">
                            @include('livewire.admin.fields.' . $actionField->component, ['field' => $actionField, 'prefix' => 'actionFields'])
                        </div>
                    @empty
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $activeAction->confirmText ?: 'Are you sure you want to continue?' }}</p>
                    @endforelse
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-navy-800 dark:bg-navy-950">
                    <button wire:click="closeAction"
                            class="ui-btn-secondary">Cancel</button>
                    <button wire:click="runDetailAction"
                            wire:loading.attr="disabled"
                            class="ui-btn-primary">
                        {{ $activeAction->confirmButtonText ?: 'Confirm' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4 backdrop-blur-sm" wire:keydown.escape="cancelDelete">
            <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-navy-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-navy-800">
                    <h3 class="text-lg font-semibold text-navy-900 dark:text-white">Delete {{ $this->resource::label() }}</h3>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-slate-600 dark:text-slate-300">Delete this record? This action can be undone by an administrator.</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-navy-800 dark:bg-navy-950">
                    <button wire:click="cancelDelete"
                            class="ui-btn-secondary">Cancel</button>
                    <button wire:click="delete"
                            class="ui-btn-danger">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
