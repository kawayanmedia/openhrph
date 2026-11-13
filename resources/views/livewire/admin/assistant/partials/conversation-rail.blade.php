<aside @class([
    'ui-card w-full shrink-0 overflow-hidden lg:w-64',
    'hidden lg:block' => ! $showHistory,
]) aria-label="Recent conversations">
    <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2.5 dark:border-navy-800">
        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Recent chats</p>
        <button type="button" wire:click="newChat" class="ui-btn-ghost !px-2 !py-1 text-xs">New</button>
    </div>

    <ul class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto dark:divide-navy-800">
        @forelse ($conversations as $chat)
            <li wire:key="conversation-{{ $chat['id'] }}">
                <button type="button" wire:click="openConversation('{{ $chat['id'] }}')"
                        @class([
                            'block w-full px-3 py-2.5 text-left transition',
                            'bg-teal-50 dark:bg-teal-500/10' => $chat['active'],
                            'hover:bg-slate-50 dark:hover:bg-navy-800' => ! $chat['active'],
                        ])>
                    <span class="block truncate text-sm font-medium text-navy-900 dark:text-slate-100">{{ $chat['title'] }}</span>
                    <span class="mt-0.5 block text-[0.7rem] text-slate-500 dark:text-slate-400">{{ $chat['when'] }}</span>
                </button>
            </li>
        @empty
            <li class="px-3 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                Your conversations will appear here.
            </li>
        @endforelse
    </ul>

    <div class="border-t border-slate-200 px-3 py-3 dark:border-navy-800">
        <p class="text-[0.7rem] leading-relaxed text-slate-500 dark:text-slate-400">
            Approvals are always confirmed by you first. The assistant cannot create or edit records.
        </p>
    </div>
</aside>
