<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Approved staffing pattern version. Items belong to a version so
 * reclassification or abolition never rewrites history.
 */
class PlantillaVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_APPROVED],
        self::STATUS_APPROVED => [self::STATUS_RETIRED],
        self::STATUS_RETIRED => [],
    ];

    protected $fillable = [
        'company_id',
        'version',
        'name',
        'approval_reference',
        'approved_by',
        'approved_at',
        'effective_from',
        'effective_until',
        'status',
        'document_id',
        'metadata',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'metadata' => 'array',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasMany<PlantillaItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlantillaItem::class, 'plantilla_version_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
