@if ($pending !== [])
    @php
        $decided = collect($pending)->filter(fn (array $approval) => in_array($decisions[$approval['id']] ?? null, ['approve', 'reject'], true))->count();
    @endphp

    <div class="border-t border-amber-200 bg-amber-50/80 px-4 py-4 dark:border-amber-500/30 dark:bg-amber-500/10">
        <div class="flex items-start gap-2.5">
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Confirmation required</p>
                <p class="mt-0.5 text-xs leading-relaxed text-amber-800 dark:text-amber-300/90">
                    Nothing has been changed yet. Review each action, then apply your decisions.
                </p>
            </div>
        </div>

        <ul class="mt-3 space-y-2">
            @foreach ($pending as $approval)
                @php
                    $decision = $decisions[$approval['id']] ?? null;
                    $verb = $approval['action'] === 'reject' ? 'Reject' : 'Approve';
                @endphp

                <li wire:key="approval-{{ $approval['id'] }}"
                    @class([
                        'rounded-xl border bg-white p-3 shadow-sm transition dark:bg-navy-900',
                        'border-teal-400 ring-1 ring-teal-300/60 dark:border-teal-500' => $decision === 'approve',
                        'border-rose-300 ring-1 ring-rose-200/70 dark:border-rose-500/70' => $decision === 'reject',
                        'border-slate-200 dark:border-navy-700' => $decision === null,
                    ])>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-navy-900 dark:text-white">
                                {{ $verb }} {{ $approval['count'] }} {{ $approval['label'] ?? 'record(s)' }}
                            </p>
                            @if ($approval['reason'])
                                <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $approval['reason'] }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            <button type="button" wire:click="decide('{{ $approval['id'] }}', 'approve')"
                                    @class([
                                        'ui-btn !px-2.5 !py-1 text-xs',
                                        'bg-teal-600 text-white hover:bg-teal-500' => $decision === 'approve',
                                        'ui-btn-secondary text-slate-600' => $decision !== 'approve',
                                    ])
                                    aria-pressed="{{ $decision === 'approve' ? 'true' : 'false' }}">
                                Approve
                            </button>
                            <button type="button" wire:click="decide('{{ $approval['id'] }}', 'reject')"
                                    @class([
                                        'ui-btn !px-2.5 !py-1 text-xs',
                                        'bg-rose-600 text-white hover:bg-rose-500' => $decision === 'reject',
                                        'ui-btn-secondary text-slate-600' => $decision !== 'reject',
                                    ])
                                    aria-pressed="{{ $decision === 'reject' ? 'true' : 'false' }}">
                                Reject
                            </button>
                        </div>
                    </div>

                    @if ($approval['rows'] !== [])
                        <ul class="mt-2.5 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-100 dark:divide-navy-800 dark:border-navy-800">
                            @foreach ($approval['rows'] as $row)
                                <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 px-3 py-1.5 text-xs">
                                    <span class="font-medium text-navy-900 dark:text-slate-100">{{ $row['employee'] ?? 'Unknown employee' }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">{{ $row['summary'] }}</span>
                                    <span class="font-mono text-[0.68rem] text-slate-400 dark:text-slate-500">#{{ $row['id'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($approval['note'])
                        <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                            <span class="font-semibold">Reason given:</span> {{ $approval['note'] }}
                        </p>
                    @endif

                    @if ($approval['missing'] !== [])
                        <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                            {{ count($approval['missing']) }} request(s) are no longer pending and will be skipped.
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" wire:click="applyDecisions" class="ui-btn-primary !py-1.5 text-xs"
                    wire:loading.attr="disabled" wire:target="applyDecisions">
                Apply {{ $decided === count($pending) ? 'decisions' : 'decisions ('.$decided.'/'.count($pending).')' }}
            </button>
            <button type="button" wire:click="fillDecisions('approve')" class="ui-btn-secondary !py-1.5 text-xs">Approve all</button>
            <button type="button" wire:click="fillDecisions('reject')" class="ui-btn-secondary !py-1.5 text-xs">Reject all</button>
        </div>
    </div>
@endif
