<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-teal-700 dark:text-slate-400 dark:hover:text-teal-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ $this->resource::pluralLabel() }}
            </a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">
                {{ $editing ? 'Edit ' . $this->resource::label() : 'Create ' . $this->resource::label() }}
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $editing ? 'Update the details below and save your changes.' : 'Add a new ' . strtolower($this->resource::label()) . ' to your workspace.' }}
            </p>
        </div>
    </div>

    <form wire:submit="save" class="ui-form-card" novalidate>
        <div class="ui-form-card-header flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-navy-900 dark:text-white">{{ $this->resource::label() }} information</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Fields marked <span class="ui-required">*</span> are required.</p>
            </div>
        </div>

        <div class="ui-form-card-body">
            @foreach ($fields as $field)
                @php($fullWidth = in_array($field->component, ['heading', 'textarea', 'trix', 'file', 'files', 'boolean-group'], true))
                <div class="{{ $fullWidth ? 'sm:col-span-6' : 'sm:col-span-3' }}">
                    @include('livewire.admin.fields.' . $field->component, ['field' => $field, 'prefix' => 'form'])
                </div>
            @endforeach
        </div>

        <div class="ui-form-footer">
            <p class="mr-auto hidden text-xs text-slate-500 dark:text-slate-400 sm:block">
                {{ $editing ? 'Saving will update this record immediately.' : 'You can edit these details again after creating the record.' }}
            </p>

            <a href="{{ url(config('admin.path') . '/resources/' . $resourceKey) }}"
               class="ui-btn-secondary">Cancel</a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="ui-btn-primary">
                <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                </svg>
                <span wire:loading.remove wire:target="save">{{ $editing ? 'Update' : 'Create' }}</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
