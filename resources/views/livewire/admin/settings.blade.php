<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">{{ $pageTitle }} settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage the {{ strtolower($pageTitle) }} settings for your organization.</p>
        </div>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row">
        <aside class="w-full shrink-0 lg:w-60">
            <nav class="ui-card overflow-hidden p-1.5" aria-label="Settings sections">
                @foreach ($pages as $key => $label)
                    <a href="{{ url(config('admin.path') . '/settings/' . $key) }}"
                       class="{{ $page === $key
                           ? 'bg-teal-50 font-semibold text-teal-800 dark:bg-teal-500/10 dark:text-teal-300'
                           : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-navy-800 dark:hover:text-white' }}
                           flex items-center justify-between rounded-lg px-3 py-2.5 text-sm transition">
                        <span>{{ $label }} settings</span>
                        @if ($page === $key)
                            <span class="h-1.5 w-1.5 rounded-full bg-teal-500" aria-hidden="true"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            <form wire:submit="save" class="ui-form-card" novalidate>
                <div class="ui-form-card-header">
                    <h2 class="text-base font-semibold text-navy-900 dark:text-white">{{ $pageTitle }} settings</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Changes apply to this company only.</p>
                </div>

                <div class="ui-form-card-body">
                    @forelse ($fields as $field)
                        @php($fullWidth = in_array($field->component, ['heading', 'textarea', 'trix', 'file', 'files', 'boolean-group'], true))
                        <div class="{{ $fullWidth ? 'sm:col-span-6' : 'sm:col-span-3' }}">
                            @include('livewire.admin.fields.' . $field->component, ['field' => $field])
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center sm:col-span-6 dark:border-navy-700">
                            <p class="text-sm text-slate-500 dark:text-slate-400">No settings fields are configured for this page.</p>
                        </div>
                    @endforelse
                </div>

                <div class="ui-form-footer">
                    <p class="mr-auto hidden text-xs text-slate-500 dark:text-slate-400 sm:block">Settings are saved per company and take effect immediately.</p>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="ui-btn-primary">
                        <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
