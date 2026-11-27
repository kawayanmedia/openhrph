<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Final-settlement inputs. Calculations live in the payroll/jurisdiction
 * layer; this record stores the reviewed itemisation and approval.
 */
class FinalSettlement extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'company_id',
        'separation_case_id',
        'calculation_reference',
        'earnings',
        'deductions',
        'total_amount',
        'status',
        'reviewed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'earnings' => 'array',
        'deductions' => 'array',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class, 'separation_case_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
