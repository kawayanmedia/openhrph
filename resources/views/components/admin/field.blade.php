@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'id' => null,
    'required' => false,
])

@php
    $fieldId = $id;
    $hasError = $error !== null && $errors->has($error);
@endphp

<div class="sm:col-span-6">
    @if ($label !== null && $label !== '')
        <label @if ($fieldId) for="{{ $fieldId }}" @endif class="ui-label">
            {{ $label }}@if ($required)<span class="ui-required" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="{{ $label !== null && $label !== '' ? 'mt-1.5' : '' }}">
        {{ $slot }}
    </div>

    @if ($help)
        <p @if ($fieldId) id="{{ $fieldId }}-help" @endif class="ui-help">{!! $help !!}</p>
    @endif

    @if ($hasError)
        @error($error)
            <p @if ($fieldId) id="{{ $fieldId }}-error" @endif class="ui-error">{{ $message }}</p>
        @enderror
    @endif
</div>
