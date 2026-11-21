@component('layouts.admin-auth', ['title' => 'Reset password'])
    <h2 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Reset your password</h2>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        Enter your work email and we will send a reset link.
    </p>

    @if (session('status'))
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('admin.password.email') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="ui-input mt-1.5" />
            @error('email')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="ui-btn-primary w-full justify-center">Email reset link</button>
    </form>

    <p class="mt-6 text-sm text-slate-600 dark:text-slate-400">
        <a href="{{ route('admin.login') }}" class="font-medium text-teal-700 hover:text-teal-800">Back to sign in</a>
    </p>
@endcomponent
