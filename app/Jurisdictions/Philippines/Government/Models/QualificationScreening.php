<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualificationScreening extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const RESULT_QUALIFIED = 'qualified';

    public const RESULT_DISQUALIFIED = 'disqualified';

    protected $fillable = [
        'company_id',
        'employee_id',
        'applicant_id',
        'qualification_standard_id',
        'standard_version',
        'evidence',
        'result',
        'deficiencies',
        'screened_by',
        'screened_at',
    ];

    protected $casts = [
        'standard_version' => 'integer',
        'evidence' => 'array',
        'deficiencies' => 'array',
        'screened_at' => 'datetime',
    ];

    public function standard(): BelongsTo
    {
        return $this->belongsTo(QualificationStandard::class, 'qualification_standard_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function isQualified(): bool
    {
        return $this->result === self::RESULT_QUALIFIED;
    }
}
