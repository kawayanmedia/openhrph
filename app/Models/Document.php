<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Document extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const CLASSIFICATION_PUBLIC = 'public';

    public const CLASSIFICATION_INTERNAL = 'internal';

    public const CLASSIFICATION_CONFIDENTIAL = 'confidential';

    public const CLASSIFICATION_RESTRICTED = 'restricted';

    public const CLASSIFICATIONS = [
        self::CLASSIFICATION_PUBLIC,
        self::CLASSIFICATION_INTERNAL,
        self::CLASSIFICATION_CONFIDENTIAL,
        self::CLASSIFICATION_RESTRICTED,
    ];

    public const CATEGORY_CONTRACT = 'contract';

    public const CATEGORY_CERTIFICATE = 'certificate';

    public const CATEGORY_GOVERNMENT = 'government_credential';

    public const CATEGORY_POLICY = 'policy';

    public const CATEGORY_LETTER = 'letter';

    public const CATEGORY_CLEARANCE = 'clearance';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_CONTRACT,
        self::CATEGORY_CERTIFICATE,
        self::CATEGORY_GOVERNMENT,
        self::CATEGORY_POLICY,
        self::CATEGORY_LETTER,
        self::CATEGORY_CLEARANCE,
        self::CATEGORY_OTHER,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id',
        'owner_type',
        'owner_id',
        'title',
        'category',
        'classification',
        'document_number',
        'description',
        'issued_at',
        'expires_at',
        'retention_until',
        'status',
        'uploaded_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'retention_until' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(160)
            ->height(160)
            ->nonQueued();
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<DocumentAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class);
    }

    /**
     * @return HasMany<DocumentAcknowledgment, $this>
     */
    public function acknowledgments(): HasMany
    {
        return $this->hasMany(DocumentAcknowledgment::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function currentVersion(): ?DocumentVersion
    {
        return $this->versions()->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSensitive(): bool
    {
        return in_array($this->classification, [self::CLASSIFICATION_CONFIDENTIAL, self::CLASSIFICATION_RESTRICTED], true);
    }
}
