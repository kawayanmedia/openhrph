@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId" :required="$field->isRequired()">
    <textarea id="{{ $fieldId }}"
              wire:model{{ $field->live ? '.live' : '' }}="{{ $name }}.{{ $field->attribute }}"
              placeholder="{{ $field->placeholder }}"
              @disabled($field->readonly)
              @required($field->isRequired())
              @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
              @error($errorKey) aria-invalid="true" @enderror
              rows="4"
              class="ui-textarea"></textarea>
</x-admin.field>
