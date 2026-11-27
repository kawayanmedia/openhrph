<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use KawayanMedia\Government\Models\Applicant;

/**
 * Candidate application to a vacancy.
 */
class Application extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_SCREENING = 'screening';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_ASSESSMENT = 'assessment';

    public const STATUS_INTERVIEW = 'interview';

    public const STATUS_RECOMMENDED = 'recommended';

    public const STATUS_OFFER = 'offer';

    public const STATUS_HIRED = 'hired';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_OFFER_DECLINED = 'offer_declined';

    public const STATUS_OFFER_EXPIRED = 'offer_expired';

    public const STATUS_TALENT_POOL = 'talent_pool';

    public const OPEN_STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_SCREENING,
        self::STATUS_SHORTLISTED,
        self::STATUS_ASSESSMENT,
        self::STATUS_INTERVIEW,
        self::STATUS_RECOMMENDED,
        self::STATUS_OFFER,
    ];

    protected $fillable = [
        'company_id',
        'job_vacancy_id',
        'applicant_id',
        'source',
        'submitted_at',
        'status',
        'current_stage',
        'disposition_reason',
        'consent_at',
        'metadata',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'consent_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    /**
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * @return HasMany<ScreeningResult, $this>
     */
    public function screenings(): HasMany
    {
        return $this->hasMany(ScreeningResult::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('scheduled_at');
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function recommendation(): HasMany
    {
        return $this->hasMany(SelectionRecommendation::class);
    }

    /**
     * @return HasMany<JobOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class)->orderByDesc('offer_version');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
