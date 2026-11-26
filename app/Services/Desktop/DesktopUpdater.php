<?php

namespace App\Services\Desktop;

use Illuminate\Support\Facades\Artisan;
use Native\Desktop\Facades\AutoUpdater;
use RuntimeException;
use Throwable;

/**
 * Drives the NativePHP auto-updater from inside the application.
 *
 * The Electron shell owns the download lifecycle: checks and downloads run in
 * the background, and `quitAndInstall()` closes the app so the downloaded
 * build can be applied. Migrations for the new build are applied by NativePHP
 * on the next launch; `runMigrations()` is the manual fallback.
 */
final class DesktopUpdater
{
    public function isSupported(): bool
    {
        return DesktopEnvironment::isRunning()
            && (bool) config('nativephp.updater.enabled', true);
    }

    public function currentVersion(): string
    {
        return (string) config('nativephp.version', '');
    }

    public function checkForUpdates(): void
    {
        $this->ensureSupported();

        AutoUpdater::checkForUpdates();
    }

    public function downloadUpdate(): void
    {
        $this->ensureSupported();

        AutoUpdater::downloadUpdate();
    }

    public function quitAndInstall(): void
    {
        $this->ensureSupported();

        AutoUpdater::quitAndInstall();
    }

    /**
     * Migration files that have not run yet.
     *
     * @return array<int, string>
     */
    public static function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $paths = array_merge($migrator->paths(), [database_path('migrations')]);
        $names = array_map(
            fn (string $file): string => $migrator->getMigrationName($file),
            $migrator->getMigrationFiles($paths),
        );

        if (! $migrator->repositoryExists()) {
            return array_values($names);
        }

        return array_values(array_diff($names, $migrator->getRepository()->getRan()));
    }

    /**
     * @return array{ok: bool, output: string}
     */
    public function runMigrations(): array
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'output' => $exception->getMessage()];
        }

        $output = trim(Artisan::output());

        return [
            'ok' => true,
            'output' => $output === '' ? 'The database schema is up to date.' : $output,
        ];
    }

    private function ensureSupported(): void
    {
        if (! $this->isSupported()) {
            throw new RuntimeException('Updates are only available in the desktop application.');
        }
    }
}
