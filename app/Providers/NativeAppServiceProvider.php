<?php

namespace App\Providers;

use App\Services\Desktop\DesktopInstaller;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;
use Throwable;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        // Keep the standard application/edit/view/window roles so copy, paste,
        // full screen and the OS window shortcuts keep working in Electron.
        Menu::create(
            Menu::app(),
            Menu::edit(),
            Menu::view(),
            Menu::window(),
        );

        Window::open()
            ->route($this->startRoute())
            ->title(config('app.name'))
            ->width(1360)
            ->height(860)
            ->minWidth(1100)
            ->minHeight(700)
            ->rememberState();
    }

    /**
     * Open the setup wizard until an owner account exists, then the login page.
     */
    private function startRoute(): string
    {
        try {
            $installed = app(DesktopInstaller::class)->isInstalled();
        } catch (Throwable) {
            $installed = false;
        }

        return $installed ? 'admin.login' : 'desktop.setup';
    }

    /**
     * Return an array of php.ini directives to be set.
     *
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'max_execution_time' => '0',
            'max_input_time' => '0',
        ];
    }
}
