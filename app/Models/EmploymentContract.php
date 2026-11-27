<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentContract extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FOR_SIGNATURE = 'for_signature';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'company_id',
        'employment_id',
        'template_name',
        'version',
        'start_date',
        'end_date',
        'signed_at',
        'signed_by_employee',
        'signed_by_company',
        'status',
        'document_id',
        'terms',
    ];

    protected $casts = [
        'version' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_at' => 'datetime',
        'terms' => 'array',
    ];

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
