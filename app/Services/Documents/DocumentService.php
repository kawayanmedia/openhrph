<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentAcknowledgment;
use App\Models\DocumentVersion;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

/**
 * Document lifecycle: immutable versions, access logging and expiry.
 */
class DocumentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Attach a file as the next immutable version of the document.
     */
    public function storeVersion(Document $document, UploadedFile|string $file, ?User $actor = null, ?string $notes = null): DocumentVersion
    {
        $media = $document->addMedia($file)->toMediaCollection('file');

        return $this->recordVersion($media, $actor, $notes);
    }

    /**
     * Idempotently record a media file as a document version.
     */
    public function recordVersion(Media $media, ?User $actor = null, ?string $notes = null): DocumentVersion
    {
        $document = $media->model;

        if (! $document instanceof Document) {
            throw new \InvalidArgumentException('Media does not belong to a document.');
        }

        $existing = DocumentVersion::withoutTenant()
            ->where('document_id', $document->getKey())
            ->where('media_id', $media->getKey())
            ->first();

        if ($existing !== null) {
            return $this->backfillVersionActor($existing, $actor, $notes);
        }

        $version = (int) DocumentVersion::withoutTenant()
            ->where('document_id', $document->getKey())
            ->max('version') + 1;

        $checksum = null;

        try {
            if (is_file($media->getPath())) {
                $checksum = hash_file('sha256', $media->getPath()) ?: null;
            }
        } catch (\Throwable) {
            $checksum = null;
        }

        $version = DocumentVersion::create([
            'company_id' => $document->company_id,
            'document_id' => $document->getKey(),
            'version' => $version,
            'media_id' => $media->getKey(),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'checksum' => $checksum,
            'uploaded_by' => $actor?->getKey(),
            'notes' => $notes,
        ]);

        $this->logAccess($document, DocumentAccessLog::ACTION_UPLOAD, $actor);

        $this->audit->record(
            'document.version_created',
            $document,
            after: $version->getAttributes(),
            metadata: ['version' => $version->version, 'media_id' => $media->getKey()],
        );

        return $version;
    }

    /**
     * Attribute an already recorded version to the actor when the listener
     * created it without one.
     */
    protected function backfillVersionActor(DocumentVersion $version, ?User $actor, ?string $notes): DocumentVersion
    {
        $updates = [];

        if ($version->uploaded_by === null && $actor !== null) {
            $updates['uploaded_by'] = $actor->getKey();
        }

        if ($version->notes === null && $notes !== null) {
            $updates['notes'] = $notes;
        }

        if ($updates === []) {
            return $version;
        }

        $version->forceFill($updates)->save();

        if (isset($updates['uploaded_by'])) {
            DocumentAccessLog::withoutTenant()
                ->where('document_id', $version->document_id)
                ->where('action', DocumentAccessLog::ACTION_UPLOAD)
                ->whereNull('user_id')
                ->update(['user_id' => $updates['uploaded_by']]);
        }

        return $version;
    }

    public function logAccess(Document $document, string $action, ?User $actor = null): DocumentAccessLog
    {
        $request = app()->bound('request') ? request() : null;

        return DocumentAccessLog::withoutTenant()->create([
            'company_id' => $document->company_id,
            'document_id' => $document->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }

    public function download(Document $document, ?User $actor = null): SymfonyStreamedResponse
    {
        $media = $document->getFirstMedia('file');

        if ($media === null) {
            abort(404, 'This document has no file.');
        }

        $this->logAccess($document, DocumentAccessLog::ACTION_DOWNLOAD, $actor);

        $disk = Storage::disk($media->disk);
        $stream = $disk->readStream($media->getPathRelativeToRoot());

        if ($stream === false) {
            abort(404, 'The document file is unavailable.');
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $media->file_name, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function acknowledge(Document $document, User $user, ?Employee $employee = null): DocumentAcknowledgment
    {
        $acknowledgment = DocumentAcknowledgment::updateOrCreate(
            [
                'document_id' => $document->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'company_id' => $document->company_id,
                'employee_id' => $employee?->getKey() ?? $user->employee?->getKey(),
                'acknowledged_at' => now(),
                'ip_address' => app()->bound('request') ? request()->ip() : null,
            ],
        );

        $this->audit->record(
            'document.acknowledged',
            $document,
            after: ['acknowledgment_id' => $acknowledgment->getKey()],
        );

        return $acknowledgment;
    }

    public function expireDueDocuments(): int
    {
        $expired = 0;

        $query = app(TenantContext::class)->id() !== null
            ? Document::query()
            : Document::withoutTenant();

        $query
            ->where('status', Document::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->orderBy('id')
            ->each(function (Document $document) use (&$expired): void {
                $document->forceFill(['status' => Document::STATUS_EXPIRED])->save();

                $this->audit->record(
                    'document.expired',
                    $document,
                    before: ['status' => Document::STATUS_ACTIVE],
                    after: ['status' => Document::STATUS_EXPIRED],
                );

                $expired++;
            });

        return $expired;
    }
}
