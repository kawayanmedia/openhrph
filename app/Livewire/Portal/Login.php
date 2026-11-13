<?php

namespace App\Livewire\Portal;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public $title = 'Login';

    public $email;

    public $password;

    public $isLoading = false;

    public $hasError = false;

    public $errorMessage = null;

    public function login()
    {
        $this->hasError = false;
        $this->errorMessage = null;

        if (Auth::guard('portal')->attempt(['email' => $this->email, 'password' => $this->password])) {
            return redirect()->route('portal.dashboard');
        }

        $this->hasError = true;
        $this->errorMessage = 'Invalid Email or Password';
    }

    public function render()
    {
        return view('livewire.portal.login.login')
            ->layout('layouts.portal-login');
    }
}
