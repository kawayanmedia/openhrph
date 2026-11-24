@php
    $companyName = portal_company_name();
    $logo = portal_company_logo();
    $user = Auth::user();
    $employee = $user?->employee;
    $requestLinks = [
        ['label' => 'My Leaves', 'route' => 'portal.leaves', 'active' => ['portal.leaves', 'portal.leaves.create']],
        ['label' => 'My Certificate of Attendance', 'route' => 'portal.attendance', 'active' => ['portal.attendance', 'portal.attendance.create']],
        ['label' => 'My Timesheet', 'route' => 'portal.timesheet', 'active' => ['portal.timesheet']],
        ['label' => 'My Schedules', 'route' => 'portal.schedules', 'active' => ['portal.schedules', 'portal.schedules.create']],
        ['label' => 'My Schedule Adjustments', 'route' => 'portal.schedulesadjustment', 'active' => ['portal.schedulesadjustment', 'portal.schedulesadjustment.create']],
        ['label' => 'My Overtimes', 'route' => 'portal.overtimes', 'active' => ['portal.overtimes', 'portal.overtimes.create']],
        ['label' => 'My Undertimes', 'route' => 'portal.undertimes', 'active' => ['portal.undertimes', 'portal.undertimes.create']],
        ['label' => 'My Official Business', 'route' => 'portal.officialbusiness', 'active' => ['portal.officialbusiness', 'portal.officialbusiness.create']],
    ];
    $linkClass = fn (bool $active): string => $active
        ? 'rounded-lg bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-700 dark:bg-teal-500/10 dark:text-teal-300'
        : 'rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-navy-800 dark:hover:text-white';
@endphp

<header x-data="{ mobileOpen: false, requestsOpen: false, userOpen: false }" class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-navy-800 dark:bg-navy-950/95">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                @if ($logo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="" class="h-9 w-9 rounded-xl object-cover">
                @else
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-sm font-bold text-teal-700 dark:text-teal-300">
                        {{ strtoupper(substr($companyName, 0, 1)) }}
                    </span>
                @endif
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold tracking-tight text-navy-900 dark:text-white">{{ $companyName }}</span>
                    <span class="block text-[11px] text-slate-500 dark:text-slate-400">Employee portal</span>
                </span>
            </a>
        </div>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Portal">
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="{{ $linkClass(request()->routeIs('portal.dashboard')) }}">Dashboard</a>

            <div class="relative" @keydown.escape.window="requestsOpen = false">
                <button type="button" @click="requestsOpen = ! requestsOpen"
                        class="{{ $linkClass(request()->routeIs('portal.leaves*', 'portal.attendance*', 'portal.timesheet*', 'portal.schedules*', 'portal.overtimes*', 'portal.undertimes*', 'portal.officialbusiness*')) }} inline-flex items-center gap-1.5">
                    My Requests
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>

                <div x-show="requestsOpen" x-cloak @click.outside="requestsOpen = false"
                     class="absolute left-0 z-40 mt-2 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-panel dark:border-navy-700 dark:bg-navy-900">
                    @foreach ($requestLinks as $link)
                        <a href="{{ route($link['route']) }}" wire:navigate
                           class="block px-4 py-2 text-sm {{ request()->routeIs(...$link['active']) ? 'bg-slate-50 font-semibold text-teal-700 dark:bg-navy-800 dark:text-teal-300' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-navy-800' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('portal.atendance.report') }}" class="{{ $linkClass(request()->routeIs('portal.atendance.report')) }}">Attendance Report</a>
            <a href="{{ route('portal.jobs') }}" class="{{ $linkClass(request()->routeIs('portal.jobs*')) }}">Job Opportunities</a>
        </nav>

        <div class="flex items-center gap-1.5">
            <button type="button"
                    onclick="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-navy-800"
                    aria-label="Toggle dark mode">
                <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15A9.75 9.75 0 1 1 9 2.25a7.5 7.5 0 0 0 12.75 12.75Z" /></svg>
                <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21m9-9h-2.25M5.25 12H3m15.36-6.36-1.6 1.6M7.24 16.76l-1.6 1.6m12.72 0-1.6-1.6M7.24 7.24l-1.6-1.6M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
            </button>

            @if ($user)
                <div class="relative hidden sm:block" @keydown.escape.window="userOpen = false">
                    <button type="button" @click="userOpen = ! userOpen"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-navy-800">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-800 dark:bg-teal-500/20 dark:text-teal-300">
                            {{ strtoupper(substr($user->name ?? $user->email ?? 'U', 0, 1)) }}
                        </span>
                        <span class="hidden max-w-36 truncate md:block">{{ $user->name ?? $user->email }}</span>
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <div x-show="userOpen" x-cloak @click.outside="userOpen = false"
                         class="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-panel dark:border-navy-700 dark:bg-navy-900">
                        <div class="border-b border-slate-100 px-4 py-3 dark:border-navy-800">
                            <p class="truncate text-sm font-semibold text-navy-900 dark:text-white">{{ $user->name ?? $user->email }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $employee?->position ?: 'Employee' }}</p>
                        </div>
                        <a href="{{ route('portal.atendance.report') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-navy-800">My attendance</a>
                        <a href="{{ route('portal.timesheet') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-navy-800">My timesheet</a>
                        <a href="{{ route('portal.signout') }}" class="block px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">Sign out</a>
                    </div>
                </div>
            @endif

            <button type="button" @click="mobileOpen = ! mobileOpen"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-navy-700 dark:text-slate-300 dark:hover:bg-navy-800 lg:hidden"
                    aria-label="Open navigation">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </button>
        </div>
    </div>

    <div x-show="mobileOpen" x-cloak class="border-t border-slate-200 bg-white px-4 py-4 dark:border-navy-800 dark:bg-navy-950 lg:hidden">
        <div class="space-y-1">
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="{{ $linkClass(request()->routeIs('portal.dashboard')) }} block">Dashboard</a>
            @foreach ($requestLinks as $link)
                <a href="{{ route($link['route']) }}" wire:navigate class="{{ $linkClass(request()->routeIs(...$link['active'])) }} block">{{ $link['label'] }}</a>
            @endforeach
            <a href="{{ route('portal.atendance.report') }}" class="{{ $linkClass(request()->routeIs('portal.atendance.report')) }} block">Attendance Report</a>
            <a href="{{ route('portal.jobs') }}" class="{{ $linkClass(request()->routeIs('portal.jobs*')) }} block">Job Opportunities</a>
            @auth
                <a href="{{ route('portal.signout') }}" class="mt-2 block rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">Sign out</a>
            @endauth
        </div>
    </div>
</header>
