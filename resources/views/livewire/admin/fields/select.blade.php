@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId" :required="$field->isRequired()">
    <x-admin.searchable-select
            id="{{ $fieldId }}"
            :model="$name . '.' . $field->attribute"
            :live="$field->live"
            :disabled="$field->readonly"
            :required="$field->isRequired()"
            :label="$field->name"
            :aria-describedby="$describedBy ?: null"
            :aria-invalid="$errors->has($errorKey) ? 'true' : null">
        <option value="">Select an option</option>
        @foreach ($field->options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </x-admin.searchable-select>
</x-admin.field>
