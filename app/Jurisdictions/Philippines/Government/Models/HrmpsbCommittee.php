<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmpsbCommittee extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'reference_number',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<HrmpsbMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(HrmpsbMember::class, 'committee_id');
    }

    /**
     * @return HasMany<RecruitmentDeliberation, $this>
     */
    public function deliberations(): HasMany
    {
        return $this->hasMany(RecruitmentDeliberation::class, 'committee_id');
    }
}
