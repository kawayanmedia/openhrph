<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionRequirement extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_EDUCATION = 'education';

    public const TYPE_TRAINING = 'training';

    public const TYPE_EXPERIENCE = 'experience';

    public const TYPE_ELIGIBILITY = 'eligibility';

    public const TYPE_LICENSE = 'license';

    public const TYPE_CERTIFICATION = 'certification';

    public const TYPE_COMPETENCY = 'competency';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_EDUCATION,
        self::TYPE_TRAINING,
        self::TYPE_EXPERIENCE,
        self::TYPE_ELIGIBILITY,
        self::TYPE_LICENSE,
        self::TYPE_CERTIFICATION,
        self::TYPE_COMPETENCY,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'company_id',
        'position_id',
        'requirement_type',
        'description',
        'reference',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
