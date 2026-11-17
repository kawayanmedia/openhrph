<div class="min-h-0 flex-1 overflow-y-auto px-4 py-4"
     x-data
     x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
     x-on:assistant-updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
     role="log"
     aria-live="polite"
     aria-relevant="additions">
    @if ($messages === [])
        <div class="mx-auto flex max-w-md flex-col items-center py-10 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
            </span>
            <h3 class="mt-4 text-base font-semibold text-navy-900 dark:text-white">Ask in plain English</h3>
            <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                I can look up employees, report logged hours for a cut-off, list pending requests, and approve or reject them —
                always after you confirm.
            </p>

            <div class="mt-5 flex w-full flex-col gap-2">
                @foreach ($suggestions as $suggestion)
                    <button type="button" wire:click="useSuggestion(@js($suggestion))"
                            class="ui-btn-secondary w-full justify-start !font-normal text-left">
                        <svg class="h-4 w-4 shrink-0 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12l-7.5 7.5M21 12H3" />
                        </svg>
                        <span class="truncate">{{ $suggestion }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($messages as $message)
                @if ($message['role'] === 'user')
                    <div wire:key="message-{{ $message['id'] }}" class="flex justify-end">
                        <div class="max-w-[85%] rounded-2xl rounded-br-md bg-navy-900 px-4 py-2.5 text-sm leading-relaxed text-white shadow-sm dark:bg-teal-500 dark:text-navy-950">
                            <p class="whitespace-pre-line">{{ $message['content'] }}</p>
                        </div>
                    </div>
                @else
                    <div wire:key="message-{{ $message['id'] }}" class="flex justify-start">
                        <div class="max-w-[90%] space-y-2">
                            <div @class([
                                'rounded-2xl rounded-bl-md border px-4 py-2.5 text-sm leading-relaxed shadow-sm',
                                'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200' => $message['failed'],
                                'border-slate-200 bg-white text-slate-700 dark:border-navy-700 dark:bg-navy-900 dark:text-slate-200' => ! $message['failed'],
                            ])>
                                @if ($message['html'])
                                    <div class="assistant-prose">{!! $message['html'] !!}</div>
                                @elseif ($message['content'] !== '')
                                    <p class="whitespace-pre-line">{{ $message['content'] }}</p>
                                @else
                                    <p class="text-slate-500 dark:text-slate-400">Working through the data…</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 px-1">
                                @foreach ($message['tools'] as $tool)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[0.68rem] font-medium text-slate-600 dark:bg-navy-800 dark:text-slate-300">
                                        <span class="h-1 w-1 rounded-full bg-teal-500"></span>
                                        {{ $tool }}
                                    </span>
                                @endforeach
                                <span class="text-[0.68rem] text-slate-400 dark:text-slate-500">{{ $message['at'] }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <div wire:loading.flex wire:target="send,applyDecisions" class="mt-4 items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <svg class="h-4 w-4 animate-spin text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z"></path>
        </svg>
        <span>Checking attendance and request data…</span>
    </div>
</div>
