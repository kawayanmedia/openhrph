<div @if ($busy) wire:poll.2000ms @endif>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Software update</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Keep this desktop installation and its database current. Releases are published on GitHub.
        </p>
    </div>

    <div class="ui-card mb-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-navy-800 sm:px-6">
            <div>
                <p class="text-sm font-semibold text-navy-900 dark:text-white">OpenHR PH</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Installed version
                    <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $version !== '' ? $version : 'unknown' }}</span>
                </p>
            </div>

            <button type="button"
                    wire:click="check"
                    @disabled(! $updaterEnabled || $busy)
                    class="ui-btn-primary disabled:cursor-not-allowed disabled:opacity-60">
                {{ $busy ? 'Working…' : 'Check for updates' }}
            </button>
        </div>

        <div class="px-5 py-4 sm:px-6">
            <div @class([
                'rounded-xl border px-4 py-3',
                'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300' => $statusTone === 'success',
                'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-300' => $statusTone === 'info',
                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300' => $statusTone === 'warning',
                'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300' => $statusTone === 'error',
                'border-slate-200 bg-slate-50 text-slate-700 dark:border-navy-700 dark:bg-navy-800/60 dark:text-slate-300' => $statusTone === 'idle',
            ])>
                <p class="text-sm font-semibold">{{ $statusTitle }}</p>
                @if ($statusDetail)
                    <p class="mt-1 text-xs opacity-90">{{ $statusDetail }}</p>
                @endif

                @if ($status['state'] === 'downloading' && $status['percent'] !== null)
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/60 dark:bg-navy-900/60"
                         role="progressbar"
                         aria-valuenow="{{ (int) $status['percent'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                        <div class="h-full rounded-full bg-current opacity-70" style="width: {{ (int) $status['percent'] }}%"></div>
                    </div>
                @endif
            </div>

            @unless ($updaterEnabled)
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Automatic updates are turned off for this build. Install new releases from the release page instead.
                </p>
            @endunless

            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if ($status['state'] === 'downloaded')
                    <button type="button"
                            wire:click="install"
                            wire:confirm="OpenHR PH will close to install version {{ $status['version'] }}. The database is migrated the next time you open the app. Continue?"
                            class="ui-btn-primary">
                        Restart and install
                    </button>
                @elseif ($status['state'] === 'available' && ! $autoDownload)
                    <button type="button" wire:click="download" class="ui-btn-primary">
                        Download update
                    </button>
                @endif

                <a href="{{ route('admin.updates') }}" class="ui-btn-secondary">
                    Refresh status
                </a>
            </div>

            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                Downloads continue in the background. When the update is ready you can install it now, or it is applied
                automatically the next time OpenHR PH quits.
            </p>
        </div>
    </div>

    <div class="ui-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-navy-800 sm:px-6">
            <div>
                <p class="text-sm font-semibold text-navy-900 dark:text-white">Database</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Migrations run automatically the first time a new version starts.
                </p>
            </div>

            <span @class([
                'ui-badge',
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => count($pendingMigrations) === 0,
                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => count($pendingMigrations) > 0,
            ])>
                {{ count($pendingMigrations) === 0
                    ? 'Up to date'
                    : count($pendingMigrations).' '.\Illuminate\Support\Str::plural('migration', count($pendingMigrations)).' pending' }}
            </span>
        </div>

        <div class="px-5 py-4 sm:px-6">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" wire:click="migrate" class="ui-btn-secondary">
                    Run migrations
                </button>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Use this if a page mentions a missing column or table after an update.
                </p>
            </div>
        </div>
    </div>
</div>
