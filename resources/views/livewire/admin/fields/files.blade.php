@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId" :required="$field->isRequired()">
    <input id="{{ $fieldId }}"
           type="file"
           multiple
           wire:model="{{ $name }}.{{ $field->attribute }}"
           @disabled($field->readonly)
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @error($errorKey) aria-invalid="true" @enderror
           class="ui-file">
</x-admin.field>
