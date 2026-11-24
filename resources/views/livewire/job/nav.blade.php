@php
    $companyName = portal_company_name();
    $logo = portal_company_logo();
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-navy-800 dark:bg-navy-950/95">
    <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('portal.jobs') }}" wire:navigate class="flex min-w-0 items-center gap-3">
            @if ($logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="" class="h-9 w-9 rounded-xl object-cover">
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-sm font-bold text-teal-700 dark:text-teal-300">{{ strtoupper(substr($companyName, 0, 1)) }}</span>
            @endif
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-navy-900 dark:text-white">{{ $companyName }}</span>
                <span class="block text-[11px] text-slate-500 dark:text-slate-400">Careers</span>
            </span>
        </a>

        <div class="flex items-center gap-2">
            <button type="button"
                    onclick="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-navy-800"
                    aria-label="Toggle dark mode">
                <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15A9.75 9.75 0 1 1 9 2.25a7.5 7.5 0 0 0 12.75 12.75Z" /></svg>
                <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21m9-9h-2.25M5.25 12H3m15.36-6.36-1.6 1.6M7.24 16.76l-1.6 1.6m12.72 0-1.6-1.6M7.24 7.24l-1.6-1.6M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
            </button>
            <a href="{{ route('portal.login') }}" class="ui-btn-secondary">Employee sign in</a>
        </div>
    </div>
</header>
