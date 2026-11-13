@php
    $name = $prefix ?? 'form';
    $errorKey = $name . '.' . $field->attribute;
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey">
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($field->options as $value => $label)
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 transition
                          hover:border-teal-300 hover:bg-teal-50/40 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50
                          dark:border-navy-700 dark:bg-navy-950 dark:text-slate-200 dark:hover:border-teal-500/60
                          dark:hover:bg-navy-900 dark:has-[:checked]:border-teal-500 dark:has-[:checked]:bg-teal-500/10">
                <input type="checkbox"
                       wire:model="{{ $name }}.{{ $field->attribute }}.{{ $value }}"
                       @disabled($field->readonly)
                       class="ui-checkbox">
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</x-admin.field>
