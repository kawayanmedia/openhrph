<?php

namespace App\Livewire\Desktop;

use App\Services\Desktop\DesktopEnvironment;
use App\Services\Desktop\DesktopInstaller;
use App\Support\Installation\Installation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin-auth')]
class Setup extends Component
{
    public string $sector = Installation::SECTOR_PRIVATE;

    public string $country = 'PH';

    public string $companyName = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(DesktopInstaller $installer): void
    {
        abort_unless(DesktopEnvironment::isRunning(), 404);

        if ($installer->isInstalled()) {
            $this->redirect(route('admin.login'), navigate: true);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'sector' => ['required', Rule::in([Installation::SECTOR_PRIVATE, Installation::SECTOR_GOVERNMENT])],
            'country' => ['required', 'string', Rule::in(array_keys((array) config('payroll.countries', [])))],
            'companyName' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function install(DesktopInstaller $installer): void
    {
        abort_unless(DesktopEnvironment::isRunning(), 404);

        $this->validate();

        try {
            $result = $installer->install([
                'sector' => $this->sector,
                'country' => strtoupper($this->country),
                'company_name' => $this->companyName,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->addError('install', $exception->getMessage());

            return;
        }

        Auth::guard('web')->login($result['user'], true);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(route('admin.dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.desktop.setup', [
            'countries' => (array) config('payroll.countries', []),
            'sectors' => [Installation::SECTOR_PRIVATE, Installation::SECTOR_GOVERNMENT],
        ]);
    }
}
