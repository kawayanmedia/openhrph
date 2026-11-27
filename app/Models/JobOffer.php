<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOffer extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'company_id',
        'application_id',
        'offer_version',
        'terms_reference',
        'salary',
        'start_date',
        'expires_at',
        'status',
        'issued_at',
        'responded_at',
        'document_id',
        'terms',
    ];

    protected $casts = [
        'offer_version' => 'integer',
        'salary' => 'integer',
        'start_date' => 'date',
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'responded_at' => 'datetime',
        'terms' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
