<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceSource extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'authority',
        'title',
        'issuance_number',
        'official_url',
        'published_at',
        'effective_from',
        'effective_until',
        'notes',
    ];

    protected $casts = [
        'published_at' => 'date',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    /**
     * @return HasMany<ComplianceRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(ComplianceRule::class);
    }
}
