<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegularizationDecision extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const DECISION_REGULARIZE = 'regularize';

    public const DECISION_EXTEND = 'extend';

    public const DECISION_SEPARATE = 'separate';

    protected $fillable = [
        'company_id',
        'employment_id',
        'decision',
        'effective_date',
        'approval_reference',
        'approved_by',
        'approved_at',
        'document_id',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
