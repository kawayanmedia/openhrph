<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetirementPlan extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const TYPE_DEFINED_BENEFIT = 'defined_benefit';

    public const TYPE_DEFINED_CONTRIBUTION = 'defined_contribution';

    public const TYPE_STATUTORY = 'statutory';

    public const TYPE_GSIS = 'gsis';

    public const TYPES = [
        self::TYPE_DEFINED_BENEFIT,
        self::TYPE_DEFINED_CONTRIBUTION,
        self::TYPE_STATUTORY,
        self::TYPE_GSIS,
    ];

    protected $fillable = [
        'company_id',
        'name',
        'sector',
        'plan_type',
        'eligibility_age',
        'minimum_service_years',
        'benefit_formula',
        'employer_rate',
        'employee_rate',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected $casts = [
        'eligibility_age' => 'integer',
        'minimum_service_years' => 'decimal:2',
        'benefit_formula' => 'array',
        'employer_rate' => 'decimal:3',
        'employee_rate' => 'decimal:3',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<RetirementCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(RetirementCase::class);
    }
}
