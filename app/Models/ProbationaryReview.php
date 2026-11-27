<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProbationaryReview extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const OUTCOME_PASS = 'pass';

    public const OUTCOME_EXTEND = 'extend';

    public const OUTCOME_FAIL = 'fail';

    protected $fillable = [
        'company_id',
        'employment_id',
        'review_date',
        'standards_reference',
        'evaluator_id',
        'outcome',
        'employee_comments',
        'supervisor_comments',
        'evidence_document_id',
    ];

    protected $casts = [
        'review_date' => 'date',
    ];

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluator_id');
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }
}
