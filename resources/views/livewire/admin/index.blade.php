<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">{{ $this->resource::pluralLabel() }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage {{ strtolower($this->resource::pluralLabel()) }} for your organization.</p>
        </div>

        @if ($canCreate)
            <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey . '/new') }}"
               class="ui-btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create {{ $this->resource::label() }}
            </a>
        @endif
    </div>

    @if (count($cards))
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($cards as $card)
                <div class="ui-card p-5">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card->name() }}</div>
                    <div class="mt-2 text-3xl font-semibold text-navy-900 dark:text-white">
                        @include('livewire.admin.partials.metric-value', ['value' => $card->calculate(request()), 'metric' => $card])
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="relative w-full max-w-md">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                </svg>
            </span>
            <input type="search"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search {{ $this->resource::pluralLabel() }}..."
                   aria-label="Search {{ $this->resource::pluralLabel() }}"
                   class="ui-input pl-10">
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if (count($toolbarActions))
                @foreach ($toolbarActions as $action)
                    <button wire:click="openAction('{{ class_basename($action) }}')"
                            class="ui-btn-secondary">
                        {{ $action->name() }}
                    </button>
                @endforeach
            @endif

            <label class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                Show
                <select wire:model.live="perPage" class="ui-select ui-select-auto py-2">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @if (count($filters))
        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-navy-800 dark:bg-navy-900">
            @foreach ($filters as $filter)
                @continue($filter->type === 'multi' && count($filter->options(request())) === 0)

                <label class="flex min-w-[10rem] flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ $filter->name() }}
                    @if ($filter->type === 'date')
                        <input type="date"
                               wire:model.live="filterValues.{{ $filter->uriKey() }}"
                               class="ui-input font-normal normal-case tracking-normal">
                    @elseif ($filter->type === 'boolean' && count($filter->options(request())) === 0)
                        <input type="checkbox"
                               wire:model.live="filterValues.{{ $filter->uriKey() }}"
                               class="ui-checkbox mt-2">
                    @elseif ($filter->type === 'multi')
                        <div class="flex flex-wrap gap-2 rounded-lg border border-slate-200 p-2 font-normal normal-case tracking-normal dark:border-navy-700">
                            @foreach ($filter->options(request()) as $value => $label)
                                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                                    <input type="checkbox"
                                           wire:model.live="filterValues.{{ $filter->uriKey() }}"
                                           value="{{ $value }}"
                                           class="ui-checkbox">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <select wire:model.live="filterValues.{{ $filter->uriKey() }}"
                                class="ui-select font-normal normal-case tracking-normal">
                            <option value="">All</option>
                            @foreach ($filter->options(request()) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                </label>
            @endforeach

            <button wire:click="clearFilters"
                    class="ui-btn-secondary">
                Clear
            </button>
        </div>
    @endif

    @if (count($selected))
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-3 py-2.5 dark:border-teal-500/30 dark:bg-teal-500/10">
            <span class="mr-1 text-sm font-semibold text-teal-800 dark:text-teal-300">{{ count($selected) }} selected</span>

            @foreach ($bulkActions as $action)
                <button wire:click="openAction('{{ class_basename($action) }}')"
                        class="ui-btn-secondary">
                    {{ $action->name() }}
                </button>
            @endforeach

            @if ($this->canDeleteSelection())
                <button wire:click="confirmDeleteSelected"
                        class="ui-btn-danger">
                    Delete selected
                </button>
            @endif

            <button wire:click="$set('selected', [])" class="ml-auto text-sm font-medium text-teal-800 hover:text-teal-900 dark:text-teal-300 dark:hover:text-teal-200">Clear selection</button>
        </div>
    @endif

    <div class="ui-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th class="w-10 px-4 py-3"></th>
                        @foreach ($columns as $column)
                            <th class="ui-th">
                                @if ($column->sortable && $column->attribute)
                                    <button wire:click="sortBy('{{ $column->attribute }}')" class="group inline-flex items-center gap-1 hover:text-teal-700 dark:hover:text-teal-400">
                                        {{ $column->name }}
                                        @if ($sortAttribute === $column->attribute)
                                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </button>
                                @else
                                    {{ $column->name }}
                                @endif
                            </th>
                        @endforeach
                        <th class="ui-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white dark:divide-navy-800 dark:bg-navy-900">
                    @forelse ($rows as $row)
                        <tr wire:key="row-{{ $row->getKey() }}" class="transition hover:bg-slate-50/80 dark:hover:bg-navy-800/60">
                            <td class="px-4 py-3">
                                <input type="checkbox"
                                       wire:model.live="selected"
                                       value="{{ $row->getKey() }}"
                                       aria-label="Select row {{ $row->getKey() }}"
                                       class="ui-checkbox">
                            </td>
                            @foreach ($columns as $column)
                                <td class="ui-td">
                                    @if ($loop->first)
                                        @if ($this->resource::allows('view', $row))
                                            <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey . '/' . $row->getKey()) }}"
                                               class="font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-400 dark:hover:text-teal-300">
                                                @include('livewire.admin.partials.field-display', ['field' => $column, 'model' => $row])
                                            </a>
                                        @else
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">
                                                @include('livewire.admin.partials.field-display', ['field' => $column, 'model' => $row])
                                            </span>
                                        @endif
                                    @else
                                        @include('livewire.admin.partials.field-display', ['field' => $column, 'model' => $row])
                                    @endif
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($this->resource::allows('view', $row))
                                    <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey . '/' . $row->getKey()) }}"
                                       class="text-sm font-medium text-teal-700 hover:text-teal-900 dark:text-teal-400 dark:hover:text-teal-300">View</a>
                                @endif
                                @if ($this->resource::allows('update', $row))
                                    <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey . '/' . $row->getKey() . '/edit') }}"
                                       class="ml-3 text-sm font-medium text-teal-700 hover:text-teal-900 dark:text-teal-400 dark:hover:text-teal-300">Edit</a>
                                @endif
                                @foreach ($actions as $action)
                                    <button wire:click="openAction('{{ class_basename($action) }}', {{ $row->getKey() }})"
                                            class="ml-3 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">{{ $action->name() }}</button>
                                @endforeach
                                @if ($this->resource::canDeleteResource($row) && $this->resource::allows('delete', $row))
                                    <button wire:click="confirmDelete({{ $row->getKey() }})"
                                            class="ml-3 text-sm font-medium text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 2 }}" class="px-4 py-16 text-center">
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">No {{ strtolower($this->resource::pluralLabel()) }} found</p>
                                <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">Try adjusting your search or filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>

    @if ($showActionModal && $activeAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4 backdrop-blur-sm" wire:keydown.escape="closeAction">
            <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl dark:bg-navy-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-navy-800">
                    <h3 class="text-lg font-semibold text-navy-900 dark:text-white">{{ $activeAction->name() }}</h3>
                </div>

                <div class="max-h-[60vh] overflow-y-auto px-6 py-5">
                    @if ($confirmingAction)
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $activeAction->confirmText ?: 'Are you sure you want to continue?' }}</p>
                    @else
                        @forelse ($actionFieldList as $actionField)
                            <div class="mb-4">
                                @include('livewire.admin.fields.' . $actionField->component, ['field' => $actionField, 'prefix' => 'actionFields'])
                            </div>
                        @empty
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $activeAction->confirmText ?: 'Are you sure you want to continue?' }}</p>
                        @endforelse
                    @endif
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-navy-800 dark:bg-navy-950">
                    <button wire:click="closeAction" class="ui-btn-secondary">Cancel</button>
                    <button wire:click="runAction"
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
                <div class="px-6 py-5">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        {{ $deletingSelected ? 'Delete the selected records?' : 'Delete this record?' }}
                        This action can be undone by an administrator.
                    </p>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-navy-800 dark:bg-navy-950">
                    <button wire:click="cancelDelete" class="ui-btn-secondary">Cancel</button>
                    <button wire:click="delete" class="ui-btn-danger">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
