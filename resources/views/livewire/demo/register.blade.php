<div>
    <div class="flex items-center gap-3 lg:hidden">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-900 text-sm font-bold text-teal-300">
            {{ strtoupper(substr(config('app.name'), 0, 1)) }}
        </span>
        <span class="text-base font-semibold text-navy-900 dark:text-white">{{ config('app.name') }}</span>
    </div>

    <h2 class="mt-6 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white lg:mt-0">Create a demo account</h2>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        Try the workspace with your own company and sample payroll data. We will email you a
        verification link before the demo opens.
    </p>

    <form wire:submit="register" class="mt-8 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Full name</label>
            <input id="name"
                   type="text"
                   wire:model="name"
                   autocomplete="name"
                   autofocus
                   required
                   class="ui-input mt-1.5" />
            @error('name')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="company_name" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Company name</label>
            <input id="company_name"
                   type="text"
                   wire:model="companyName"
                   autocomplete="organization"
                   required
                   class="ui-input mt-1.5"
                   placeholder="Demo Company" />
            @error('companyName')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Email address</label>
            <input id="email"
                   type="email"
                   wire:model="email"
                   autocomplete="username"
                   required
                   class="ui-input mt-1.5" />
            @error('email')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
            <input id="password"
                   type="password"
                   wire:model="password"
                   autocomplete="new-password"
                   required
                   class="ui-input mt-1.5" />
            @error('password')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Confirm password</label>
            <input id="password_confirmation"
                   type="password"
                   wire:model="password_confirmation"
                   autocomplete="new-password"
                   required
                   class="ui-input mt-1.5" />
        </div>

        <button type="submit"
                wire:loading.attr="disabled"
                class="ui-btn-primary w-full py-2.5">
            <svg wire:loading wire:target="register" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
            <span wire:loading.remove wire:target="register">Create demo account</span>
            <span wire:loading wire:target="register">Creating your demo…</span>
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">
        The demo workspace is disposable and only contains sample records. Do not enter real employee
        or payroll data.
    </p>

    <p class="mt-8 text-sm text-slate-600 dark:text-slate-300">
        Already have an account?
        <a href="{{ route('admin.login') }}" class="font-medium text-teal-700 hover:text-teal-800 dark:text-teal-400">
            Sign in
        </a>
    </p>
</div>
