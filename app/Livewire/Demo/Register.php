<?php

namespace App\Livewire\Demo;

use App\Services\Demo\DemoTenantProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin-auth')]
class Register extends Component
{
    public string $name = '';

    public string $companyName = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'companyName' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function register(DemoTenantProvisioner $provisioner): void
    {
        $key = 'demo-register:'.request()->ip();
        $maxAttempts = (int) config('demo.registrations_per_hour', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError('email', "Too many demo accounts from this address. Try again in {$seconds} seconds.");

            return;
        }

        $this->validate();

        $user = $provisioner->provision([
            'name' => $this->name,
            'company_name' => $this->companyName,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        RateLimiter::hit($key, 3600);

        Auth::guard('web')->login($user);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            // The notice page can resend once the mail configuration is fixed.
            report($e);
        }

        $this->redirect(route('verification.notice'), navigate: true);
    }

    public function render()
    {
        return view('livewire.demo.register')->title('Create a demo account');
    }
}
