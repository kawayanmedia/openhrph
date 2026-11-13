<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use BelongsToTenant;

    protected $table = 'applicants';

    protected $fillable = [
        'name',
        'gender',
        'address',
        'birth_date',
        'educational_attainment',
        'position_applying',
        'telephone',
        'email',
        'eligibility',
        'training',
        'experience',
        'training_file',
        'experience_file',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Applicant $model) {
            set_current_timestamp($model);
        });
    }

    public function jobVacancy()
    {
        return $this->belongsTo(JobVacancy::class);
    }

    public function getTrainingUrlAttribute()
    {
        if ($this->training_file != null) {
            return 'https://mrdq-bucket.s3.ap-southeast-1.amazonaws.com/'.$this->training_file;
        }

        return '';
    }

    public function getExperienceUrlAttribute()
    {
        if ($this->experience_file != null) {
            return 'https://mrdq-bucket.s3.ap-southeast-1.amazonaws.com/'.$this->experience_file;
        }

        return '';
    }

    public function getPositionApplyingAttribute($value)
    {
        if ($this->jobVacancy != null) {
            return $this->jobVacancy->plantillaItem?->position_title;
        }

        return $value;
    }
}
