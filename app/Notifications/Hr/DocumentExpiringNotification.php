<?php

namespace App\Notifications\Hr;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public Document $document) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Document expiring',
            'message' => $this->document->title.' expires on '.$this->document->expires_at?->toFormattedDateString().'.',
            'document_id' => $this->document->getKey(),
            'url' => route('admin.resource.detail', [
                'resource' => 'document',
                'resourceId' => $this->document->getKey(),
            ]),
        ];
    }
}
