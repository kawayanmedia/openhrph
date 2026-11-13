<div>
@if ($companies->count() > 1)
    <div class="relative" x-data="{ open: false }">
        <button type="button"
                @click="open = ! open"
                class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-navy-700 dark:text-slate-300 dark:hover:bg-navy-800">
            <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
            <span class="max-w-40 truncate">
                {{ $companies->firstWhere('id', (int) $activeCompanyId)?->name ?? 'Select company' }}
            </span>
            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div x-show="open"
             x-cloak
             @click.outside="open = false"
             class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-panel dark:border-navy-700 dark:bg-navy-900">
            @foreach ($companies as $company)
                <button type="button"
                        wire:click="switchTo({{ $company->id }})"
                        class="flex w-full items-center justify-between px-4 py-2 text-left text-sm {{ (int) $company->id === (int) $activeCompanyId ? 'bg-slate-50 font-semibold text-teal-700 dark:bg-navy-800 dark:text-teal-300' : 'text-slate-700 dark:text-slate-200' }} hover:bg-slate-50 dark:hover:bg-navy-800">
                    <span class="truncate">{{ $company->name }}</span>
                    @if ((int) $company->id === (int) $activeCompanyId)
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
@endif
</div>
