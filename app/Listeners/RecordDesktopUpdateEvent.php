<?php

namespace App\Listeners;

use App\Services\Desktop\DesktopUpdateState;
use Native\Desktop\Events\AutoUpdater\CheckingForUpdate;
use Native\Desktop\Events\AutoUpdater\DownloadProgress;
use Native\Desktop\Events\AutoUpdater\Error;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Events\AutoUpdater\UpdateCancelled;
use Native\Desktop\Events\AutoUpdater\UpdateDownloaded;
use Native\Desktop\Events\AutoUpdater\UpdateNotAvailable;

/**
 * Stores the auto-updater events the Electron shell sends back to Laravel so
 * the update screen can show what the updater is doing.
 */
class RecordDesktopUpdateEvent
{
    public function __invoke(object $event): void
    {
        match (true) {
            $event instanceof CheckingForUpdate => DesktopUpdateState::put(
                DesktopUpdateState::STATE_CHECKING,
            ),
            $event instanceof UpdateAvailable => DesktopUpdateState::put(
                DesktopUpdateState::STATE_AVAILABLE,
                [
                    'version' => $event->version,
                    'release_notes' => $this->releaseNotes($event->releaseNotes),
                ],
            ),
            $event instanceof UpdateNotAvailable => DesktopUpdateState::put(
                DesktopUpdateState::STATE_UP_TO_DATE,
                ['version' => $event->version],
            ),
            $event instanceof DownloadProgress => DesktopUpdateState::put(
                DesktopUpdateState::STATE_DOWNLOADING,
                ['percent' => round($event->percent, 1)],
            ),
            $event instanceof UpdateDownloaded => DesktopUpdateState::put(
                DesktopUpdateState::STATE_DOWNLOADED,
                ['version' => $event->version, 'percent' => 100.0],
            ),
            $event instanceof UpdateCancelled => DesktopUpdateState::put(
                DesktopUpdateState::STATE_CANCELLED,
                ['version' => $event->version],
            ),
            $event instanceof Error => DesktopUpdateState::put(
                DesktopUpdateState::STATE_ERROR,
                ['message' => $event->message],
            ),
            default => null,
        };
    }

    /**
     * GitHub release notes arrive as a string or as per-version payloads.
     *
     * @param  string|array<int, mixed>|null  $notes
     */
    private function releaseNotes(string|array|null $notes): ?string
    {
        if (is_string($notes)) {
            return $notes;
        }

        if (is_array($notes) && isset($notes[0]['note'])) {
            return (string) $notes[0]['note'];
        }

        return null;
    }
}
