<div x-data="{ open: false, active: 0 }"
     @open-global-search.window="open = true; active = 0; $nextTick(() => { $refs.input.focus(); $refs.input.select(); })"
     @keydown.escape.window="open = false"
     @keydown.arrow-down.prevent="if (open) { active = Math.min(active + 1, ($refs.results?.querySelectorAll('a').length ?? 1) - 1); $refs.results?.querySelectorAll('a')[active]?.focus(); }"
     @keydown.arrow-up.prevent="if (open) { active = Math.max(0, active - 1); $refs.results?.querySelectorAll('a')[active]?.focus(); }">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center bg-navy-950/60 p-4 pt-20 backdrop-blur-sm"
             @click.self="open = false" role="dialog" aria-modal="true" aria-label="Search">
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-navy-700 dark:bg-navy-900">
                <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3 dark:border-navy-800">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                    </svg>
                    <input x-ref="input" type="search" wire:model.live.debounce.200ms="query"
                           class="w-full border-0 bg-transparent p-0 text-base text-navy-900 placeholder:text-slate-400 focus:ring-0 dark:text-white"
                           placeholder="Search people, companies, payroll, pages…" autocomplete="off">
                    <button type="button" wire:click="clear" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" aria-label="Clear search">Clear</button>
                    <kbd class="hidden rounded border border-slate-200 px-1.5 py-0.5 text-[10px] text-slate-400 dark:border-navy-700 sm:block">esc</kbd>
                </div>

                <div x-ref="results" class="max-h-[60vh] overflow-y-auto px-2 py-2">
                    @if (trim($query) === '')
                        <div class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            Type at least two characters. Use <kbd class="rounded border border-slate-200 px-1 text-[10px] dark:border-navy-700">↑</kbd> <kbd class="rounded border border-slate-200 px-1 text-[10px] dark:border-navy-700">↓</kbd> to move and Enter to open.
                        </div>
                    @elseif ($groups === [])
                        <div class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            No matches for “{{ $query }}”.
                        </div>
                    @else
                        @foreach ($groups as $group => $items)
                            <div class="px-2 pb-2">
                                <p class="px-2 pt-2 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $group }}</p>
                                @foreach ($items as $item)
                                    <a href="{{ $item['url'] }}" wire:navigate
                                       class="flex items-start gap-3 rounded-lg px-3 py-2 transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none dark:hover:bg-navy-800 dark:focus:bg-navy-800">
                                        <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-teal-500"></span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-navy-900 dark:text-white">{{ $item['label'] }}</span>
                                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $item['description'] }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 px-4 py-2 text-[11px] text-slate-400 dark:border-navy-800">
                    <span>Search across navigation, actions and records</span>
                    <span class="hidden sm:block">⌘/ or Ctrl+/</span>
                </div>
            </div>
        </div>
    </template>
</div>
