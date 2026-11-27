<div class="relative">
    <button type="button" wire:click="toggle"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-navy-800"
            aria-label="Notifications">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.85 23.85 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.311 6.022c1.733.64 3.56 1.085 5.454 1.31m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-panel dark:border-navy-700 dark:bg-navy-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2 dark:border-navy-800">
                <p class="text-sm font-semibold text-navy-900 dark:text-white">Notifications</p>
                <button type="button" wire:click="markAllRead" class="text-xs text-teal-700 hover:underline dark:text-teal-400">
                    Mark all read
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse ($notifications as $notification)
                    <button type="button" wire:key="notification-{{ $notification->id }}"
                            wire:click="markRead('{{ $notification->id }}')"
                            class="block w-full border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50 dark:border-navy-800 dark:hover:bg-navy-800">
                        <p class="text-sm font-medium {{ $notification->read_at ? 'text-slate-600 dark:text-slate-300' : 'text-navy-900 dark:text-white' }}">
                            {{ $notification->data['title'] ?? 'Notification' }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $notification->data['message'] ?? '' }}
                        </p>
                        <p class="mt-1 text-[10px] uppercase tracking-wide text-slate-400">
                            {{ $notification->created_at?->diffForHumans() }}
                        </p>
                    </button>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">No notifications.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
