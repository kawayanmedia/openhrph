@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId" :required="$field->isRequired()">
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
</x-admin.field>
