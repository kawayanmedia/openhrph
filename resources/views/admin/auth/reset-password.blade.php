@component('layouts.admin-auth', ['title' => 'Choose a new password'])
    <h2 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Choose a new password</h2>

    <form method="POST" action="{{ route('admin.password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="ui-input mt-1.5" />
            @error('email')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-200">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="ui-input mt-1.5" />
            @error('password')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="ui-input mt-1.5" />
        </div>

        <button type="submit" class="ui-btn-primary w-full justify-center">Reset password</button>
    </form>
@endcomponent
