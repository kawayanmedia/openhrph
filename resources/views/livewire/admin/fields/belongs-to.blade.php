@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId" :required="$field->isRequired()">
    <div class="flex items-center gap-2">
        <select id="{{ $fieldId }}"
                wire:model{{ $field->live ? '.live' : '' }}="{{ $name }}.{{ $field->attribute }}"
                @disabled($field->readonly)
                @required($field->isRequired())
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @error($errorKey) aria-invalid="true" @enderror
                class="ui-select">
            <option value="">Select an option</option>
            @foreach ($field->options as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>

        @php($relatedKey = collect([
            \Illuminate\Support\Str::kebab($field->name),
            \Illuminate\Support\Str::kebab(\Illuminate\Support\Str::pluralStudly($field->name)),
        ])->first(fn ($key) => \App\Livewire\Admin\Support\ResourceRegistry::find($key)))

        @if ($field->showCreateRelationButton && $relatedKey)
            <a href="{{ url(config('admin.path') . '/resources/' . $relatedKey) }}"
               class="ui-btn-secondary h-11 w-11 shrink-0 px-0"
               title="Manage {{ $field->name }}"
               aria-label="Manage {{ $field->name }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </a>
        @endif
    </div>
</x-admin.field>
