<div @class([
    'flex' => true,
    $variant === 'page' ? 'flex-col gap-6 lg:flex-row lg:items-start' : 'h-full flex-col',
])>
    @if ($variant === 'page')
        @include('livewire.admin.assistant.partials.conversation-rail')
    @endif

    <section @class([
        'ui-card flex min-w-0 flex-1 flex-col overflow-hidden',
        $variant === 'page' ? 'min-h-[36rem]' : 'h-full rounded-none border-0 shadow-none',
    ])>
        <header class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-navy-800">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-navy-900 dark:text-white">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 4.5c.53 2.06 2.127 3.657 4.187 4.187-2.06.53-3.657 2.127-4.187 4.187-.53-2.06-2.127-3.657-4.187-4.187 2.06-.53 3.657-2.127 4.187-4.187ZM18 13.5c.31 1.203 1.297 2.19 2.5 2.5-1.203.31-2.19 1.297-2.5 2.5-.31-1.203-1.297-2.19-2.5-2.5 1.203-.31 2.19-1.297 2.5-2.5ZM6.75 16.5c.207.803.947 1.543 1.75 1.75-.803.207-1.543.947-1.75 1.75-.207-.803-.947-1.543-1.75-1.75.803-.207 1.543-.947 1.75-1.75Z" />
                        </svg>
                    </span>
                    HR assistant
                </h2>
                <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"
                   title="Hours, requests and approvals{{ $periodLabel ? ' · period '.$periodLabel : '' }}">
                    Hours, requests and approvals{{ $periodLabel ? ' · period '.$periodLabel : '' }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                @if ($variant === 'page')
                    <button type="button" wire:click="toggleHistory" class="ui-btn-ghost !px-2 !py-1.5 text-xs lg:hidden">
                        Chats
                    </button>
                    <button type="button" wire:click="newChat" class="ui-btn-ghost !px-2.5 !py-1.5 text-xs">
                        New chat
                    </button>
                @else
                    <button type="button" wire:click="newChat" class="ui-btn-ghost !px-2.5 !py-1.5 text-xs">
                        New
                    </button>
                    <button type="button" x-on:click="$dispatch('assistant-close')"
                            class="ui-btn-ghost !px-2 !py-1.5" aria-label="Close assistant">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </header>

        @if (! $providerReady)
            <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                No API key is configured for the AI provider. Add one to <code class="font-mono">config/ai.php</code> before using the assistant.
            </div>
        @endif

        @if ($error)
            <div class="flex items-start justify-between gap-3 border-b border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300" role="alert">
                <span>{{ $error }}</span>
                <button type="button" wire:click="dismissError" class="opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
            </div>
        @endif

        @include('livewire.admin.assistant.partials.transcript')

        @include('livewire.admin.assistant.partials.approvals')

        @include('livewire.admin.assistant.partials.composer')
    </section>
</div>
