<?php

namespace App\Services\Documents\Listeners;

use App\Models\Document;
use App\Services\Documents\DocumentService;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Every file attached to a document becomes an immutable version.
 */
class RecordDocumentVersion
{
    public function __construct(private readonly DocumentService $documents) {}

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        if (! $event->media->model instanceof Document) {
            return;
        }

        $this->documents->recordVersion($event->media);
    }
}
