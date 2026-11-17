@php
    $name = $prefix ?? 'form';
    $fieldId = \Illuminate\Support\Str::slug($name . '-' . ($field->attribute ?? $field->name));
    $errorKey = $name . '.' . $field->attribute;
    $describedBy = trim(($field->help ? $fieldId . '-help' : '') . ' ' . ($errors->has($errorKey) ? $fieldId . '-error' : ''));
@endphp

<x-admin.field :label="$field->name" :help="$field->help" :error="$errorKey" :id="$fieldId" :required="$field->isRequired()">
    @if ($field->currency)
        <div class="ui-input-group">
            <span class="ui-input-addon">{{ $field->currency }}</span>
            <input id="{{ $fieldId }}"
                   type="number"
                   step="{{ $field->step ?? '0.01' }}"
                   wire:model{{ $field->live ? '.live' : '' }}="{{ $name }}.{{ $field->attribute }}"
                   placeholder="{{ $field->placeholder }}"
                   @disabled($field->readonly)
                   @required($field->isRequired())
                   @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                   @error($errorKey) aria-invalid="true" @enderror
                   class="ui-input">
        </div>
    @else
        <input id="{{ $fieldId }}"
               type="number"
               step="{{ $field->step ?? '0.01' }}"
               wire:model{{ $field->live ? '.live' : '' }}="{{ $name }}.{{ $field->attribute }}"
               placeholder="{{ $field->placeholder }}"
               @disabled($field->readonly)
               @required($field->isRequired())
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @error($errorKey) aria-invalid="true" @enderror
               class="ui-input">
    @endif
</x-admin.field>
