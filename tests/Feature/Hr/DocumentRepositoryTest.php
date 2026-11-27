<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentVersion;
use App\Models\Employee;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function createDocumentRepositoryDocument(Company $company, array $attributes = []): Document
{
    return app(TenantContext::class)->run($company->id, fn (): Document => Document::create(array_merge([
        'title' => 'Service Contract',
        'category' => Document::CATEGORY_CONTRACT,
        'classification' => Document::CLASSIFICATION_INTERNAL,
        'status' => Document::STATUS_ACTIVE,
    ], $attributes)));
}

beforeEach(function (): void {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);

    $this->tenant = app(TenantContext::class);
    $this->company = Company::factory()->create();
    $this->service = app(DocumentService::class);
});

it('stores the first version with checksum, mime type, size and media id plus an upload log', function () {
    $uploader = User::factory()->forCompany($this->company)->create();
    $document = createDocumentRepositoryDocument($this->company, ['uploaded_by' => $uploader->id]);

    $content = "%PDF-1.4\n% service contract\n%%EOF\n";

    $version = $this->tenant->run($this->company->id, fn (): DocumentVersion => $this->service->storeVersion(
        $document,
        UploadedFile::fake()->createWithContent('contract.pdf', $content),
        $uploader,
        'Initial upload',
    ));

    expect($version->version)->toBe(1)
        ->and($version->document_id)->toBe($document->id)
        ->and($version->company_id)->toBe($this->company->id)
        ->and($version->media_id)->not->toBeNull()
        ->and($version->media_id)->toBe($document->getFirstMedia('file')?->getKey())
        ->and($version->file_name)->toBe('contract.pdf')
        ->and($version->mime_type)->toBe('application/pdf')
        ->and($version->size)->toBe(strlen($content))
        ->and($version->checksum)->toBe(hash('sha256', $content))
        ->and($version->uploaded_by)->toBe($uploader->id);

    $this->tenant->run($this->company->id, function () use ($document, $uploader): void {
        $logs = $document->accessLogs()->where('action', DocumentAccessLog::ACTION_UPLOAD)->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->user_id)->toBe($uploader->id);
    });
});

it('keeps the previous version when a second file is stored', function () {
    $uploader = User::factory()->forCompany($this->company)->create();
    $document = createDocumentRepositoryDocument($this->company, ['uploaded_by' => $uploader->id]);

    [$first, $second] = $this->tenant->run($this->company->id, fn (): array => [
        $this->service->storeVersion($document, UploadedFile::fake()->create('contract.pdf', 5, 'application/pdf'), $uploader),
        $this->service->storeVersion($document, UploadedFile::fake()->create('contract-v2.pdf', 7, 'application/pdf'), $uploader),
    ]);

    expect($second->version)->toBe(2)
        ->and($second->version)->toBeGreaterThan($first->version)
        ->and($second->file_name)->toBe('contract-v2.pdf');

    $this->tenant->run($this->company->id, function () use ($document, $first, $second): void {
        expect($document->versions()->count())->toBe(2);

        expect(DocumentVersion::query()->whereKey($first->getKey())->exists())->toBeTrue()
            ->and(DocumentVersion::query()->whereKey($second->getKey())->exists())->toBeTrue();
    });
});

it('records a media file as a version only once', function () {
    $uploader = User::factory()->forCompany($this->company)->create();
    $document = createDocumentRepositoryDocument($this->company, ['uploaded_by' => $uploader->id]);

    $this->tenant->run($this->company->id, function () use ($document, $uploader): void {
        // Attaching media fires the global listener, which records the version.
        $media = $document->addMedia(UploadedFile::fake()->create('policy.pdf', 3, 'application/pdf'))
            ->toMediaCollection('file');

        expect($document->versions()->count())->toBe(1);

        $recorded = $this->service->recordVersion($media, $uploader);

        expect($recorded->version)->toBe(1)
            ->and($document->versions()->count())->toBe(1)
            ->and($this->service->recordVersion($media, $uploader)->getKey())->toBe($recorded->getKey());
    });
});

it('restricts sensitive documents to the owner, uploader or permitted group', function () {
    $owner = User::factory()->forCompany($this->company)->owner()->create();
    $uploader = User::factory()->forCompany($this->company)->create();
    $colleague = User::factory()->forCompany($this->company)->create();

    $restricted = createDocumentRepositoryDocument($this->company, [
        'classification' => Document::CLASSIFICATION_RESTRICTED,
        'uploaded_by' => $uploader->id,
    ]);

    $confidential = createDocumentRepositoryDocument($this->company, [
        'classification' => Document::CLASSIFICATION_CONFIDENTIAL,
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($colleague);

    expect(Gate::forUser($colleague)->allows('view', $restricted))->toBeFalse()
        ->and(Gate::forUser($colleague)->allows('download', $restricted))->toBeFalse();

    expect(Gate::forUser($uploader)->allows('view', $restricted))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $restricted))->toBeTrue()
        ->and(Gate::forUser($colleague)->allows('view', $confidential))->toBeFalse();

    foreach ([Document::CLASSIFICATION_PUBLIC, Document::CLASSIFICATION_INTERNAL] as $classification) {
        $open = createDocumentRepositoryDocument($this->company, [
            'classification' => $classification,
            'uploaded_by' => $uploader->id,
        ]);

        expect($colleague->can('view', $open))->toBeTrue()
            ->and($colleague->can('download', $open))->toBeTrue();
    }
});

it('denies access to documents owned by another tenant', function () {
    $otherCompany = Company::factory()->create();
    $intruder = User::factory()->forCompany($otherCompany)->create();

    $document = createDocumentRepositoryDocument($this->company, [
        'classification' => Document::CLASSIFICATION_PUBLIC,
    ]);

    $this->actingAs($intruder);

    expect(Gate::forUser($intruder)->allows('view', $document))->toBeFalse()
        ->and(Gate::forUser($intruder)->allows('download', $document))->toBeFalse();
});

it('expires active documents whose expiry date has passed', function () {
    $due = createDocumentRepositoryDocument($this->company, [
        'title' => 'Lapsed Permit',
        'expires_at' => now()->subDay()->toDateString(),
    ]);

    $future = createDocumentRepositoryDocument($this->company, [
        'title' => 'Valid Permit',
        'expires_at' => now()->addYear()->toDateString(),
    ]);

    $noExpiry = createDocumentRepositoryDocument($this->company, ['title' => 'Permanent Record']);

    expect($this->service->expireDueDocuments())->toBe(1);

    expect($due->fresh()?->status)->toBe(Document::STATUS_EXPIRED)
        ->and($due->fresh()?->isExpired())->toBeTrue()
        ->and($future->fresh()?->status)->toBe(Document::STATUS_ACTIVE)
        ->and($noExpiry->fresh()?->status)->toBe(Document::STATUS_ACTIVE);
});

it('acknowledges a document once per user', function () {
    $user = User::factory()->forCompany($this->company)->create();
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $document = createDocumentRepositoryDocument($this->company);

    $this->tenant->run($this->company->id, function () use ($document, $user, $employee): void {
        $first = $this->service->acknowledge($document, $user, $employee);
        $second = $this->service->acknowledge($document, $user, $employee);

        expect($second->getKey())->toBe($first->getKey())
            ->and($document->acknowledgments()->count())->toBe(1)
            ->and($second->employee_id)->toBe($employee->id)
            ->and($second->acknowledged_at)->not->toBeNull();
    });
});
