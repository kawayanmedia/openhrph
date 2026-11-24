@php
    $user = Auth::user();
    $employee = $employee ?? $user?->employee;
    $displayName = $employee?->first_name ?: ($user->name ?? 'there');
    $leaveStats = [
        ['label' => 'Vacation leave', 'value' => $employee?->total_vacation_leave ?? 0, 'tone' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300'],
        ['label' => 'Sick leave', 'value' => $employee?->total_sick_leave ?? 0, 'tone' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'],
        ['label' => 'Personal leave', 'value' => $employee?->total_personal_leave ?? 0, 'tone' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'],
    ];
    $actions = [
        ['label' => 'File a leave', 'description' => 'Vacation, sick or personal leave', 'route' => 'portal.leaves.create', 'tone' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300', 'icon' => 'calendar'],
        ['label' => 'File attendance', 'description' => 'Certificate of attendance request', 'route' => 'portal.attendance.create', 'tone' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'icon' => 'clock'],
        ['label' => 'View timesheet', 'description' => 'Punch logs and daily hours', 'route' => 'portal.timesheet', 'tone' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'icon' => 'document'],
        ['label' => 'File overtime', 'description' => 'Request approval for extra hours', 'route' => 'portal.overtimes.create', 'tone' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'icon' => 'spark'],
        ['label' => 'File undertime', 'description' => 'Log a shortened work day', 'route' => 'portal.undertimes.create', 'tone' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300', 'icon' => 'arrow-down'],
        ['label' => 'Official business', 'description' => 'Request time out of office', 'route' => 'portal.officialbusiness.create', 'tone' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300', 'icon' => 'briefcase'],
        ['label' => 'Schedule adjustment', 'description' => 'Request a schedule change', 'route' => 'portal.schedulesadjustment.create', 'tone' => 'bg-slate-100 text-slate-700 dark:bg-navy-800 dark:text-slate-200', 'icon' => 'refresh'],
        ['label' => 'Attendance report', 'description' => 'Print your attendance record', 'route' => 'portal.atendance.report', 'tone' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300', 'icon' => 'printer'],
    ];
@endphp

<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-panel dark:border-navy-800 dark:bg-navy-900">
        <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-teal-500/15 text-lg font-bold text-teal-700 dark:text-teal-300">
                    {{ strtoupper(substr($displayName, 0, 1)) }}
                </span>
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Welcome back,</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">{{ $displayName }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $employee?->position ?: 'Employee' }}{{ $employee?->office?->name ? ' · '.$employee->office->name : '' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('portal.leaves.create') }}" wire:navigate class="ui-btn-primary">File a request</a>
                <a href="{{ route('portal.timesheet') }}" wire:navigate class="ui-btn-secondary">My timesheet</a>
            </div>
        </div>

        <div class="grid grid-cols-1 divide-y divide-slate-200 border-t border-slate-200 bg-slate-50 dark:divide-navy-800 dark:border-navy-800 dark:bg-navy-950/40 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            @foreach ($leaveStats as $stat)
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-navy-900 dark:text-white">{{ $stat['value'] }}</p>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $stat['tone'] }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <div class="mb-3 flex items-end justify-between">
            <div>
                <h2 class="text-lg font-semibold text-navy-900 dark:text-white">Quick actions</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Everything you need for the current cut-off.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($actions as $action)
                <a href="{{ route($action['route']) }}" wire:navigate
                   class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-panel dark:border-navy-800 dark:bg-navy-900 dark:hover:border-teal-500/40">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $action['tone'] }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                    <p class="mt-4 text-sm font-semibold text-navy-900 group-hover:text-teal-700 dark:text-white dark:group-hover:text-teal-300">{{ $action['label'] }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $action['description'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="ui-card lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-navy-800">
                <div>
                    <h2 class="text-base font-semibold text-navy-900 dark:text-white">Announcements</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Company updates for employees.</p>
                </div>
                @if ($showViewAll && count($announcements) > 0)
                    <button type="button" wire:click="viewAll" class="ui-btn-ghost text-xs">View all</button>
                @endif
            </div>

            @if (count($announcements) === 0)
                <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No announcements yet.</div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-navy-800">
                    @foreach ($announcements as $announcement)
                        <li class="px-5 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-navy-900 dark:text-white">{{ $announcement->title }}</p>
                                @if ($announcement->priority)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $announcement->priority }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit(strip_tags((string) $announcement->content), 220) }}</p>
                            <p class="mt-2 text-xs text-slate-400">{{ $announcement->created_at?->format('M d, Y') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="ui-card p-5">
            <h2 class="text-base font-semibold text-navy-900 dark:text-white">Need help?</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Common employee tasks and where to find them.</p>

            <div class="mt-4 space-y-3 text-sm">
                <a href="{{ route('portal.schedules') }}" wire:navigate class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:border-teal-200 hover:text-teal-700 dark:border-navy-800 dark:text-slate-200 dark:hover:border-teal-500/40 dark:hover:text-teal-300">
                    <span>My schedule</span><span aria-hidden="true">&rarr;</span>
                </a>
                <a href="{{ route('portal.attendance') }}" wire:navigate class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:border-teal-200 hover:text-teal-700 dark:border-navy-800 dark:text-slate-200 dark:hover:border-teal-500/40 dark:hover:text-teal-300">
                    <span>Attendance requests</span><span aria-hidden="true">&rarr;</span>
                </a>
                <a href="{{ route('portal.officialbusiness') }}" wire:navigate class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:border-teal-200 hover:text-teal-700 dark:border-navy-800 dark:text-slate-200 dark:hover:border-teal-500/40 dark:hover:text-teal-300">
                    <span>Official business</span><span aria-hidden="true">&rarr;</span>
                </a>
                <a href="{{ route('portal.jobs') }}" wire:navigate class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:border-teal-200 hover:text-teal-700 dark:border-navy-800 dark:text-slate-200 dark:hover:border-teal-500/40 dark:hover:text-teal-300">
                    <span>Job opportunities</span><span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    </section>
</div>
