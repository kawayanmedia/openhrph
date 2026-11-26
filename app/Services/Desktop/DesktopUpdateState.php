<?php

namespace App\Services\Desktop;

use Illuminate\Support\Facades\Cache;

/**
 * The latest auto-updater state reported by the Electron shell.
 *
 * Electron posts updater events back to Laravel, but the desktop profile uses
 * the log broadcasting driver, so the events cannot reach the UI directly. The
 * listener stores them here and the update screen polls this cache.
 */
final class DesktopUpdateState
{
    public const STATE_IDLE = 'idle';

    public const STATE_CHECKING = 'checking';

    public const STATE_AVAILABLE = 'available';

    public const STATE_UP_TO_DATE = 'up-to-date';

    public const STATE_DOWNLOADING = 'downloading';

    public const STATE_DOWNLOADED = 'downloaded';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_ERROR = 'error';

    private const CACHE_KEY = 'desktop.updater.state';

    /**
     * @return array{state: string, version: string|null, percent: float|null, release_notes: string|null, message: string|null, updated_at: string|null}
     */
    public static function get(): array
    {
        $state = Cache::get(self::CACHE_KEY);

        return is_array($state) ? $state + self::blank() : self::blank();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function put(string $state, array $attributes = []): void
    {
        Cache::put(self::CACHE_KEY, array_merge(self::blank(), $attributes, [
            'state' => $state,
            'updated_at' => now()->toIso8601String(),
        ]), now()->addDay());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Whether the shell is still busy with a check or a download.
     */
    public static function isBusy(string $state): bool
    {
        return in_array($state, [self::STATE_CHECKING, self::STATE_DOWNLOADING], true);
    }

    /**
     * @return array{state: string, version: string|null, percent: float|null, release_notes: string|null, message: string|null, updated_at: string|null}
     */
    private static function blank(): array
    {
        return [
            'state' => self::STATE_IDLE,
            'version' => null,
            'percent' => null,
            'release_notes' => null,
            'message' => null,
            'updated_at' => null,
        ];
    }
}
