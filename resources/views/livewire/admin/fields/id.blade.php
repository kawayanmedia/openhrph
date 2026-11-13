@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId">
    <input id="{{ $fieldId }}"
           type="text"
           value="{{ $form[$field->attribute] ?? '' }}"
           disabled
           class="ui-input bg-slate-50 font-mono text-slate-500 dark:bg-navy-900">
</x-admin.field>
