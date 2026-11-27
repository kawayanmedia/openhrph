<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceTask extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'company_id',
        'separation_case_id',
        'unit',
        'task',
        'assignee_user_id',
        'status',
        'evidence_document_id',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class, 'separation_case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }
}
