<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Getting started</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Finish these steps to run your first payroll{{ $readiness['focus_group'] ? ' for '.$readiness['focus_group'] : '' }}.
            </p>
        </div>

        <button type="button" wire:click="$refresh" class="ui-btn-secondary">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m-4.992 4.992 3.181-3.183a8.25 8.25 0 0 0-13.5 6.057M4.5 14.25v4.992h4.992m-4.992 0 3.181-3.183a8.25 8.25 0 0 0 13.5-6.057" />
            </svg>
            Refresh status
        </button>
    </div>

    @php
        $isReady = $readiness['is_ready'];
        $nextAction = $readiness['next_action'];
    @endphp

    <div class="ui-card mb-6 overflow-hidden">
        <div class="grid grid-cols-1 items-center gap-6 p-5 sm:p-6 lg:grid-cols-3">
            <div class="flex items-center gap-5">
                <div class="relative h-20 w-20 shrink-0">
                    <svg class="h-20 w-20 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3.5"
                                class="text-slate-200 dark:text-navy-700"></circle>
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3.5"
                                stroke-linecap="round" pathLength="100"
                                stroke-dasharray="{{ $readiness['percent'] }} 100"
                                class="{{ $isReady ? 'text-emerald-500' : 'text-teal-500' }}"></circle>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-lg font-semibold text-navy-900 dark:text-white">
                        {{ $readiness['percent'] }}%
                    </span>
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Required steps</p>
                    <p class="mt-0.5 text-2xl font-semibold text-navy-900 dark:text-white">
                        {{ $readiness['required_complete'] }} <span class="text-base font-medium text-slate-400">of</span> {{ $readiness['required_total'] }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if ($isReady)
                            All required setup is complete.
                        @else
                            {{ $readiness['required_pending'] }} {{ \Illuminate\Support\Str::plural('step', $readiness['required_pending']) }} still to do before a payroll run.
                        @endif
                    </p>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-navy-800" role="progressbar"
                     aria-valuenow="{{ $readiness['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full {{ $isReady ? 'bg-emerald-500' : 'bg-teal-500' }}"
                         style="width: {{ $readiness['percent'] }}%"></div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $readiness['recommended_complete'] }} of {{ $readiness['recommended_total'] }} recommended steps complete
                    </p>

                    @if ($isReady)
                        @php
                            $runSection = collect($readiness['sections'])->firstWhere('key', 'run');
                            $runAction = $runSection['items'][0]['action'] ?? null;
                        @endphp
                        <a href="{{ $runAction['url'] ?? route('admin.resource.create', ['resource' => 'pay-roll']) }}" class="ui-btn-primary">
                            {{ $runAction['label'] ?? 'Create payroll run' }}
                        </a>
                    @elseif ($nextAction !== null && $nextAction['url'] !== null)
                        <a href="{{ $nextAction['url'] }}" class="ui-btn-primary">
                            {{ $nextAction['label'] }}
                        </a>
                    @elseif ($nextAction !== null && ($nextAction['method'] ?? null) === 'generatePayrollDates')
                        <button type="button" wire:click="generatePayrollDates" class="ui-btn-primary">
                            {{ $nextAction['label'] }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (count($readiness['warnings']))
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <p class="font-semibold">Before you run payroll</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($readiness['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isReady)
        <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Payroll is ready to run</p>
                <p class="text-xs text-emerald-700 dark:text-emerald-400">All required setup is in place. Open the payroll module to preview and process the period.</p>
            </div>
        </div>
    @endif

    <div class="space-y-6">
        @foreach ($readiness['sections'] as $section)
            <section class="ui-card" aria-labelledby="setup-section-{{ $section['key'] }}">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-navy-800 sm:px-6">
                    <h2 id="setup-section-{{ $section['key'] }}" class="text-base font-semibold text-navy-900 dark:text-white">
                        {{ $section['label'] }}
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $section['description'] }}</p>
                </div>

                <ul class="divide-y divide-slate-100 dark:divide-navy-800">
                    @foreach ($section['items'] as $item)
                        @php
                            $status = $item['status'];
                            $complete = $status === 'complete';
                            $upcoming = $status === 'upcoming';
                            $badge = match ($status) {
                                'complete' => ['Done', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
                                'upcoming' => ['Scheduled', 'bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-300'],
                                'pending' => ['Required', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
                                default => ['Recommended', 'bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-300'],
                            };
                        @endphp

                        <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:gap-4 sm:px-6">
                            <span @class([
                                'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' => $complete,
                                'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' => $status === 'pending',
                                'bg-slate-100 text-slate-500 dark:bg-navy-800 dark:text-slate-400' => $upcoming || $status === 'optional',
                            ])>
                                @if ($complete)
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                @elseif ($upcoming)
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                @else
                                    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-navy-900 dark:text-white">{{ $item['title'] }}</p>
                                    <span class="ui-badge {{ $badge[1] }}">{{ $badge[0] }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $item['description'] }}</p>
                                @if ($item['meta'])
                                    <p class="mt-1 text-xs font-medium text-slate-400 dark:text-slate-500">{{ $item['meta'] }}</p>
                                @endif
                            </div>

                            @if ($item['action'])
                                <div class="shrink-0 sm:pt-0.5">
                                    @if (($item['action']['method'] ?? null) === 'generatePayrollDates')
                                        <button type="button" wire:click="generatePayrollDates" class="ui-btn-secondary w-full sm:w-auto">
                                            {{ $item['action']['label'] }}
                                        </button>
                                    @else
                                        <a href="{{ $item['action']['url'] }}"
                                           class="{{ $complete ? 'ui-btn-ghost' : 'ui-btn-secondary' }} w-full sm:w-auto">
                                            {{ $item['action']['label'] }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</div>
