<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Writes the environment file the packaged desktop app boots from.
 *
 * electron-builder drops dotfiles from the package, so the `.env` NativePHP
 * copies into the build directory never reaches the installed app and every
 * request fails with "No application encryption key has been specified".
 * This exports the same settings to a regular file that ships, which
 * bootstrap/app.php loads when no `.env` is present.
 */
class ExportDesktopEnvironment extends Command
{
    protected $signature = 'desktop:export-env
        {--source= : Environment file to export, defaults to .env}
        {--output= : Destination file, defaults to bootstrap/desktop.env}';

    protected $description = 'Export the environment settings the packaged desktop app needs';

    public function handle(): int
    {
        $source = $this->option('source') ?: base_path('.env');
        $output = $this->option('output') ?: base_path('bootstrap/desktop.env');

        if (! is_file($source)) {
            $this->error('No environment file to export at ['.$source.'].');

            return self::FAILURE;
        }

        $removed = array_merge(
            ['LOG_CHANNEL', 'LOG_STACK', 'LOG_DAILY_DAYS', 'LOG_LEVEL'],
            (array) config('nativephp.cleanup_env_keys', []),
        );

        $settings = collect(file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(fn (string $line): string => trim($line))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '#'))
            ->reject(fn (string $line): bool => Str::is($removed, Str::before($line, '=')))
            ->values();

        $appKey = $settings->first(fn (string $line): bool => Str::startsWith($line, 'APP_KEY='));

        if ($appKey === null || Str::after($appKey, 'APP_KEY=') === '') {
            $this->error('APP_KEY is missing from ['.$source.']. The packaged app cannot boot without it; run `php artisan key:generate` first.');

            return self::FAILURE;
        }

        $contents = $settings
            ->push('LOG_CHANNEL=stack')
            ->push('LOG_STACK=daily')
            ->push('LOG_DAILY_DAYS=3')
            ->push('LOG_LEVEL=warning')
            ->implode(PHP_EOL);

        if (! is_dir(dirname($output))) {
            $this->error('Directory ['.dirname($output).'] does not exist.');

            return self::FAILURE;
        }

        file_put_contents($output, $contents.PHP_EOL);

        $this->info('Wrote ['.$output.'] with '.$settings->count().' settings.');

        return self::SUCCESS;
    }
}
