<div>
    <div class="flex items-center gap-3 lg:hidden">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-900 text-sm font-bold text-teal-300">
            {{ strtoupper(substr(config('app.name'), 0, 1)) }}
        </span>
        <span class="text-base font-semibold text-navy-900 dark:text-white">{{ config('app.name') }}</span>
    </div>

    <h2 class="mt-6 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white lg:mt-0">Sign in to the workspace</h2>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        Use your company account. Need access? Ask an administrator to add you to a group with admin permissions.
    </p>

    <form wire:submit="authenticate" class="mt-8 space-y-5">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Email address</label>
            <input id="email"
                   type="email"
                   wire:model="email"
                   autocomplete="username"
                   autofocus
                   required
                   class="ui-input mt-1.5" />
            @error('email')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div x-data="{ show: false }">
            <div class="flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
                <a href="{{ url('/app/password/reset') }}" class="text-sm font-medium text-teal-700 hover:text-teal-800 dark:text-teal-400">
                    Forgot password?
                </a>
            </div>

            <div class="relative mt-1.5">
                <input id="password"
                       :type="show ? 'text' : 'password'"
                       wire:model="password"
                       autocomplete="current-password"
                       required
                       class="ui-input pr-16" />
                <button type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-2 my-auto h-7 rounded-md px-2 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-navy-800"
                        :aria-label="show ? 'Hide password' : 'Show password'">
                    <span x-text="show ? 'Hide' : 'Show'"></span>
                </button>
            </div>

            @error('password')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" wire:model="remember" class="ui-checkbox">
            Keep me signed in on this device
        </label>

        <button type="submit"
                wire:loading.attr="disabled"
                class="ui-btn-primary w-full py-2.5">
            <svg wire:loading wire:target="authenticate" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
            <span wire:loading.remove wire:target="authenticate">Sign in</span>
            <span wire:loading wire:target="authenticate">Signing in…</span>
        </button>
    </form>

    <div class="mt-8 flex items-center justify-between text-sm">
        <button type="button"
                onclick="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';"
                class="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-navy-800">
            Toggle theme
        </button>
    </div>
</div>
