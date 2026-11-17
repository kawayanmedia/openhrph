@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId" :required="$field->isRequired()">
    <input id="{{ $fieldId }}"
           type="number"
           wire:model{{ $field->live ? '.live' : '' }}="{{ $name }}.{{ $field->attribute }}"
           placeholder="{{ $field->placeholder }}"
           @disabled($field->readonly)
           @required($field->isRequired())
           @if ($field->min !== null) min="{{ $field->min }}" @endif
           @if ($field->max !== null) max="{{ $field->max }}" @endif
           @if ($field->step !== null) step="{{ $field->step }}" @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @error($errorKey) aria-invalid="true" @enderror
           class="ui-input">
</x-admin.field>
