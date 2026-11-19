<div>
    <div class="flex items-center gap-3 lg:hidden">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-900 text-sm font-bold text-teal-300">
            {{ strtoupper(substr(config('app.name'), 0, 1)) }}
        </span>
        <span class="text-base font-semibold text-navy-900 dark:text-white">{{ config('app.name') }}</span>
    </div>

    <h2 class="mt-6 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white lg:mt-0">Verify your email</h2>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        We sent a verification link to
        <span class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()?->email }}</span>.
        Open the link in that email to unlock the demo workspace.
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="mt-6 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-300">
            A new verification link has been sent to your email address.
        </div>
    @endif

    @error('email')
        <p class="mt-6 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror

    <div class="mt-8 space-y-3">
        <button type="button"
                wire:click="resend"
                wire:loading.attr="disabled"
                class="ui-btn-primary w-full py-2.5">
            <span wire:loading.remove wire:target="resend">Resend verification email</span>
            <span wire:loading wire:target="resend">Sending…</span>
        </button>

        <button type="button"
                wire:click="logout"
                class="ui-btn-secondary w-full py-2.5">
            Sign out
        </button>
    </div>

    <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">
        The link expires after {{ config('auth.verification.expire') }} minutes. You can request a new one above.
    </p>
</div>
