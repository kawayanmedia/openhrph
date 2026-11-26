<?php

namespace App\Livewire\Admin;

use App\Services\Desktop\DesktopEnvironment;
use App\Services\Desktop\DesktopUpdater;
use App\Services\Desktop\DesktopUpdateState;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
class SoftwareUpdate extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
        abort_unless(DesktopEnvironment::isRunning(), 404);
    }

    /**
     * Ask the Electron shell to look for a newer release.
     */
    public function check(DesktopUpdater $updater): void
    {
        $this->authorizeOwner();

        try {
            $updater->checkForUpdates();
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('admin.error', $exception->getMessage());

            return;
        }

        DesktopUpdateState::put(DesktopUpdateState::STATE_CHECKING);

        session()->flash('admin.saved', 'Checking GitHub for a newer release…');
    }

    /**
     * Download the release the shell reported, when auto-download is off.
     */
    public function download(DesktopUpdater $updater): void
    {
        $this->authorizeOwner();

        try {
            $updater->downloadUpdate();
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('admin.error', $exception->getMessage());

            return;
        }

        DesktopUpdateState::put(DesktopUpdateState::STATE_DOWNLOADING);

        session()->flash('admin.saved', 'Downloading the update in the background…');
    }

    /**
     * Close the app so the downloaded build replaces the installed one.
     */
    public function install(DesktopUpdater $updater): void
    {
        $this->authorizeOwner();

        try {
            $updater->quitAndInstall();
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('admin.error', $exception->getMessage());

            return;
        }

        session()->flash('admin.saved', 'OpenHR PH will restart to finish installing the update.');
    }

    /**
     * Apply migrations the update may have brought with it.
     */
    public function migrate(DesktopUpdater $updater): void
    {
        $this->authorizeOwner();

        $result = $updater->runMigrations();

        session()->flash($result['ok'] ? 'admin.saved' : 'admin.error', $result['output']);
    }

    public function render(DesktopUpdater $updater)
    {
        $status = DesktopUpdateState::get();
        $version = $updater->currentVersion();
        [$tone, $title, $detail] = $this->statusCopy($status, $version);

        return view('livewire.admin.software-update', [
            'status' => $status,
            'statusTone' => $tone,
            'statusTitle' => $title,
            'statusDetail' => $detail,
            'busy' => DesktopUpdateState::isBusy($status['state']),
            'version' => $version,
            'updaterEnabled' => (bool) config('nativephp.updater.enabled', true),
            'autoDownload' => (bool) config('nativephp.updater.auto_download', true),
            'pendingMigrations' => DesktopUpdater::pendingMigrations(),
        ])->layout('layouts.admin')->title('Software update');
    }

    /**
     * @param  array{state: string, version: string|null, percent: float|null, release_notes: string|null, message: string|null, updated_at: string|null}  $status
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function statusCopy(array $status, string $version): array
    {
        return match ($status['state']) {
            DesktopUpdateState::STATE_CHECKING => [
                'info',
                'Checking for updates',
                'This takes a few seconds. OpenHR PH looks for releases on GitHub.',
            ],
            DesktopUpdateState::STATE_AVAILABLE => [
                'info',
                'Version '.$status['version'].' is available',
                $status['release_notes'] ?? 'The download starts automatically.',
            ],
            DesktopUpdateState::STATE_UP_TO_DATE => [
                'success',
                'You are running the latest version',
                'Version '.$status['version'].' is the newest release.',
            ],
            DesktopUpdateState::STATE_DOWNLOADING => [
                'info',
                'Downloading version '.$status['version'],
                $status['percent'] === null
                    ? 'The download continues in the background.'
                    : $status['percent'].'% downloaded.',
            ],
            DesktopUpdateState::STATE_DOWNLOADED => [
                'success',
                'Version '.$status['version'].' is ready to install',
                'Restart OpenHR PH to finish installing. The database is migrated on the next launch.',
            ],
            DesktopUpdateState::STATE_CANCELLED => [
                'warning',
                'The update was cancelled',
                'Check again when you have a stable connection.',
            ],
            DesktopUpdateState::STATE_ERROR => [
                'error',
                'The update could not be checked',
                $status['message'] ?? 'Try again later.',
            ],
            default => [
                'idle',
                'No update check has run yet',
                'Check for updates to compare this install (version '.$version.') with the latest release.',
            ],
        };
    }

    private function authorizeOwner(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
        abort_unless(DesktopEnvironment::isRunning(), 404);
    }
}
