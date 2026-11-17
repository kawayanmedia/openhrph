<?php

namespace App\Livewire\Admin\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin-auth')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::guard('web')->check() && Auth::guard('web')->user()?->can(config('admin.gate'))) {
            $this->redirectIntended(route('admin.dashboard'), navigate: true);
        }
    }

    public function authenticate(): void
    {
        $this->validate();

        $key = 'admin-login:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::guard('web')->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);

            $this->password = '';

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->can(config('admin.gate'))) {
            Auth::guard('web')->logout();
            $this->password = '';

            throw ValidationException::withMessages([
                'email' => 'Your account does not have access to the admin panel. Contact your administrator.',
            ]);
        }

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirectIntended(route('admin.dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.auth.login')->title('Sign in');
    }
}
