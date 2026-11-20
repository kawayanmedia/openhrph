<?php

namespace App\Services\Desktop;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Runtime profile for the NativePHP desktop build.
 *
 * NativePHP runs Laravel inside Electron with a bundled static PHP binary and a
 * local SQLite database. The web deployment keeps MySQL, the Go payroll engine
 * and the server mail/broadcast configuration, so every override here is opt-in
 * and only applied when the app detects the native runtime.
 */
final class DesktopEnvironment
{
    public static function isRunning(): bool
    {
        if ((bool) config('nativephp-internal.running', false)) {
            return true;
        }

        return filter_var(env('NATIVEPHP_RUNNING', false), FILTER_VALIDATE_BOOL);
    }

    public static function debugEnabled(): bool
    {
        return (bool) config('desktop.debug', false);
    }

    /**
     * Show errors, stack traces and DevTools in a packaged build.
     *
     * NativePHP keys its per-user storage and SQLite paths off app.debug, so
     * this runs after that switch has been read and only flips Laravel's
     * debug mode for the rest of the request. Development runs already have
     * app.debug enabled and are left alone.
     */
    public static function enableDebug(): void
    {
        if (! self::isRunning() || ! self::debugEnabled() || config('app.debug')) {
            return;
        }

        config([
            'app.debug' => true,
            // NativePHP forces LOG_LEVEL=warning into the bundled .env.
            'logging.channels.daily.level' => 'debug',
            'logging.channels.single.level' => 'debug',
        ]);
    }

    /**
     * Create the SQLite schema on a fresh install.
     *
     * NativePHP only runs `migrate` when its stored app version changes, so an
     * existing install that lost its database (or a rebuilt app shipped under
     * the same version) would serve pages against an empty file. Every page —
     * and the setup wizard — needs the cache, session and users tables.
     */
    public static function ensureDatabaseIsMigrated(): void
    {
        if (! self::isRunning()) {
            return;
        }

        try {
            if (Schema::hasTable('migrations')) {
                return;
            }

            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            // Leave the failure in the log; the setup wizard reports it with
            // the command output when the owner retries the installation.
            report($exception);
        }
    }

    public static function apply(): void
    {
        if (! self::isRunning()) {
            return;
        }

        config([
            // The bundled PHP binary has no pdo_mysql and the Go engine reads
            // MySQL directly, so the parity-locked PHP driver runs payroll.
            'payroll.engine.driver' => 'php',

            // A desktop installation is a single private workspace.
            'demo.enabled' => false,

            // Nothing leaves the machine: no SMTP server and no WebSockets.
            'mail.default' => 'log',
            'broadcasting.default' => 'log',

            // NativePHP's SQLite connection and storage paths are per-user.
            'queue.default' => 'database',
            'session.driver' => 'file',

            // The packaged public/ directory is read-only, so uploaded media is
            // served by Laravel from the writable storage path instead of a
            // storage:link symlink. A relative URL keeps the port NativePHP
            // assigns on each launch.
            'filesystems.disks.public.serve' => true,
            'filesystems.disks.public.url' => '/storage',

            'telescope.enabled' => false,
        ]);
    }
}
