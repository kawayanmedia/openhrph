<div>
    <div class="flex items-center gap-3 lg:hidden">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-900 text-sm font-bold text-teal-300">
            {{ strtoupper(substr(config('app.name'), 0, 1)) }}
        </span>
        <span class="text-base font-semibold text-navy-900 dark:text-white">{{ config('app.name') }}</span>
    </div>

    <h2 class="mt-6 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white lg:mt-0">Set up {{ config('app.name') }}</h2>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        This is the first run on this computer. Choose how your organisation runs payroll and create
        the owner account. All data stays on this device.
    </p>

    <form wire:submit="install" class="mt-8 space-y-5">
        @error('install')
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
                {{ $message }}
            </div>
        @enderror

        <div>
            <label for="sector" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Sector</label>
            <select id="sector" wire:model="sector" class="ui-input mt-1.5">
                @foreach ($sectors as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                The sector is fixed for this installation. It controls statutory agencies and the
                payroll catalogues.
            </p>
            @error('sector')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="country" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Payroll country</label>
            <select id="country" wire:model="country" class="ui-input mt-1.5">
                @foreach ($countries as $code => $profile)
                    <option value="{{ $code }}">{{ $profile['name'] ?? $code }}</option>
                @endforeach
            </select>
            @error('country')
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
                   placeholder="Your company" />
            @error('companyName')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Your name</label>
            <input id="name"
                   type="text"
                   wire:model="name"
                   autocomplete="name"
                   required
                   class="ui-input mt-1.5" />
            @error('name')
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
            <svg wire:loading wire:target="install" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
            <span wire:loading.remove wire:target="install">Create workspace</span>
            <span wire:loading wire:target="install">Setting up your workspace…</span>
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">
        Reference data, statutory tables, and the payroll calendar are installed for you, along with a
        sample workspace: one office, one department, a default schedule, three sample employees
        (D-0001 to D-0003), their attendance and a draft payroll. Edit or delete those records once you
        add your own.
    </p>
</div>
