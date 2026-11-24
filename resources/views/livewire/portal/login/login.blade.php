<div class="ui-card p-6 sm:p-8">
    <div class="mb-6">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-500/15 text-sm font-bold text-teal-700 dark:text-teal-300">
            {{ strtoupper(substr(portal_company_name(), 0, 1)) }}
        </span>
        <h2 class="mt-4 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Sign in to your account</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use the e-mail address on your employee record.</p>
    </div>

    @if ($hasError)
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-5">
        <div>
            <label for="email" class="ui-label">Email address</label>
            <input id="email" type="email" wire:model="email" autocomplete="email" required class="ui-input" placeholder="you@company.com">
            @error('email') <p class="ui-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="ui-label">Password</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="ui-input" placeholder="••••••••">
            @error('password') <p class="ui-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" class="ui-checkbox" name="remember">
            Remember me on this device
        </label>

        <button type="submit" class="ui-btn-primary w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
        Forgot your password? Contact your HR administrator.
    </p>

    <div class="mt-6 border-t border-slate-200 pt-4 text-center dark:border-navy-800">
        <a href="{{ route('portal.jobs') }}" wire:navigate class="text-sm font-medium text-teal-700 hover:underline dark:text-teal-300">View job opportunities</a>
    </div>
</div>
