@props([
    'label' => null,
    'model' => null,
    'live' => false,
    'placeholder' => 'Select an option',
    'threshold' => 8,
    'disabled' => false,
    'required' => false,
    'wrapperClass' => '',
])

@php
    $optionsHtml = (string) $slot;
    $optionCount = substr_count($optionsHtml, '<option');
    $placeholderCount = (int) preg_match_all('/<option\b[^>]*\bvalue\s*=\s*("\s*"|\'\s*\')/i', $optionsHtml);
    $searchable = ($optionCount - $placeholderCount) > (int) $threshold;

    $modelAttribute = $model ? ($live ? 'wire:model.live' : 'wire:model') : null;
    $selectId = $attributes->get('id');
    $triggerId = $selectId ? $selectId . '-trigger' : null;
    $describedBy = $attributes->get('aria-describedby');
    $invalid = $attributes->get('aria-invalid');
    $nativeClasses = trim('ui-select ' . $attributes->get('class'));
    $triggerClasses = trim('ui-select text-left ' . $attributes->get('class'));
    $wrapperClasses = trim('relative ' . $wrapperClass);
@endphp

@if (! $searchable)
    <select {{ $attributes->except('class') }}
            @if ($modelAttribute) {!! $modelAttribute !!}="{{ $model }}" @endif
            class="{{ $nativeClasses }}"
            @disabled($disabled)
            @required($required)>
        {{ $slot }}
    </select>
@else
    <div class="{{ $wrapperClasses }}"
         x-data="searchableSelect({ placeholder: @js($placeholder) })"
         @scroll.window="open && positionPanel()"
         @resize.window="open && positionPanel()">
        <select x-ref="native"
                {{ $attributes->except(['class', 'aria-describedby', 'aria-invalid']) }}
                @if ($modelAttribute) {!! $modelAttribute !!}="{{ $model }}" @endif
                tabindex="-1"
                aria-hidden="true"
                @disabled($disabled)
                @required($required)
                @focus="handleNativeFocus()"
                @click.prevent
                class="pointer-events-none absolute inset-0 h-full w-full opacity-0">
            {{ $slot }}
        </select>

        <button type="button"
                x-ref="trigger"
                @if ($triggerId) id="{{ $triggerId }}" @endif
                role="combobox"
                aria-haspopup="listbox"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-controls="listboxId"
                @if ($label) aria-label="{{ $label }}" @endif
                @if ($required) aria-required="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($invalid) aria-invalid="{{ $invalid }}" @endif
                @disabled($disabled)
                @click="toggle()"
                @keydown.arrow-down.prevent="openList()"
                @keydown.arrow-up.prevent="openList()"
                @keydown.enter.prevent="openList()"
                @keydown.space.prevent="openList()"
                @keydown.escape.prevent="close()"
                class="{{ $triggerClasses }}">
            <span class="block truncate" x-text="label"></span>
        </button>

        <template x-teleport="body">
            <div x-ref="panel"
                 x-show="open"
                 x-cloak
                 role="dialog"
                 @if ($label) aria-label="{{ $label }}" @endif
                 :style="`top: ${position.top}px; left: ${position.left}px; width: ${position.width}px;`"
                 class="fixed z-[60] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-panel dark:border-navy-700 dark:bg-navy-900">
                <div class="border-b border-slate-100 p-2 dark:border-navy-800">
                    <input type="search"
                           x-ref="search"
                           x-model="query"
                           @input="activeIndex = 0"
                           @keydown.arrow-down.prevent="move(1)"
                           @keydown.arrow-up.prevent="move(-1)"
                           @keydown.enter.prevent="choose(activeOption)"
                           @keydown.escape.prevent="close({ restoreFocus: true })"
                           @keydown.tab="close()"
                           :aria-controls="listboxId"
                           :aria-activedescendant="activeOption ? `${listboxId}-option-${activeIndex}` : null"
                           class="ui-input py-2"
                           placeholder="Search options…"
                           aria-label="Search options"
                           autocomplete="off">
                </div>

                <div x-ref="results"
                     :id="listboxId"
                     role="listbox"
                     @if ($label) aria-label="{{ $label }}" @endif
                     class="max-h-64 overflow-y-auto py-1">
                    <template x-for="(option, index) in visibleOptions" :key="`${option.value}-${index}`">
                        <div role="option"
                             :id="`${listboxId}-option-${index}`"
                             :data-index="index"
                             :aria-selected="option.value === value ? 'true' : 'false'"
                             :class="index === activeIndex
                                 ? 'bg-teal-50 text-teal-900 dark:bg-teal-500/10 dark:text-teal-200'
                                 : 'text-slate-700 dark:text-slate-200'"
                             class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-navy-800"
                             @click="choose(option)"
                             @mouseenter="activeIndex = index">
                            <span class="truncate" x-text="option.label || option.value"></span>
                            <svg x-show="option.value === value" class="h-4 w-4 shrink-0 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                    </template>

                    <p x-show="visibleOptions.length === 0" class="px-3 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        No matches. Try a different search.
                    </p>
                </div>

                <p x-show="matches.length > visibleOptions.length"
                   class="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-400 dark:border-navy-800 dark:text-slate-500"
                   x-text="`Showing ${visibleOptions.length} of ${matches.length} — keep typing to narrow`"></p>
            </div>
        </template>
    </div>
@endif
