<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningResult extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const RESULT_QUALIFIED = 'qualified';

    public const RESULT_DISQUALIFIED = 'disqualified';

    protected $fillable = [
        'company_id',
        'application_id',
        'criteria_version',
        'result',
        'notes',
        'reviewer_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isQualified(): bool
    {
        return $this->result === self::RESULT_QUALIFIED;
    }
}
