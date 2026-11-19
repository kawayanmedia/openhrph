<?php

namespace App\Livewire\Demo;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin-auth')]
class VerifyEmailNotice extends Component
{
    public function mount(): void
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && $user->hasVerifiedEmail()) {
            $this->redirectIntended(route('admin.dashboard'), navigate: true);
        }
    }

    public function resend(): void
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            $this->redirect(route('admin.login'), navigate: true);

            return;
        }

        $key = 'demo-verification:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError('email', "Too many requests. Try again in {$seconds} seconds.");

            return;
        }

        RateLimiter::hit($key, 600);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            $this->addError('email', 'We could not send the verification email. Please try again later.');

            return;
        }

        session()->flash('status', 'verification-link-sent');
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $this->redirect(route('admin.login'), navigate: true);
    }

    public function render()
    {
        return view('livewire.demo.verify-email')->title('Verify your email');
    }
}
