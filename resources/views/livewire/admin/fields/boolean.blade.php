@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="null" :help="$field->help" :error="$errorKey" :id="$fieldId">
    <div class="flex items-center gap-3">
        <label for="{{ $fieldId }}" class="relative inline-flex cursor-pointer items-center">
            <input id="{{ $fieldId }}"
                   type="checkbox"
                   wire:model{{ $field->live ? '.live' : '' }}="{{ $name }}.{{ $field->attribute }}"
                   @disabled($field->readonly)
                   @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                   @error($errorKey) aria-invalid="true" @enderror
                   class="peer sr-only">
            <span class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-teal-600 peer-focus-visible:ring-2 peer-focus-visible:ring-teal-500/40 peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 dark:bg-navy-700 dark:peer-focus-visible:ring-offset-navy-950
                         after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
        </label>

        <label for="{{ $fieldId }}" class="cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ $field->name }}
        </label>
    </div>
</x-admin.field>
