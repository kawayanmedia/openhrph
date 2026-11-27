<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KawayanMedia\Government\Models\JobVacancy;

class RecruitmentDeliberation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'job_vacancy_id',
        'committee_id',
        'meeting_date',
        'reference_number',
        'minutes_document_id',
        'recommendation',
        'status',
    ];

    protected $casts = [
        'meeting_date' => 'date',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(HrmpsbCommittee::class, 'committee_id');
    }

    public function minutes(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'minutes_document_id');
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }
}
