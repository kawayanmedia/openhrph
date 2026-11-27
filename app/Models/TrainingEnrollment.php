<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEnrollment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_NOMINATED = 'nominated';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_ATTENDED = 'attended';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'company_id',
        'training_session_id',
        'employee_id',
        'status',
        'completion_date',
        'score',
        'evaluation',
        'certificate_document_id',
    ];

    protected $casts = [
        'completion_date' => 'date',
        'score' => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function certificateDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'certificate_document_id');
    }
}
