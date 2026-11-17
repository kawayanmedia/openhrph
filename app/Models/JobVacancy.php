<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobVacancy extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = ['plantilla_item_id', 'summary', 'type'];

    protected $table = 'job_vacancies';

    protected $casts = ['date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (JobVacancy $model) {
            set_current_timestamp($model);
        });
    }

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class);
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getTotalApplicantsAttribute()
    {
        return $this->applicants->count();
    }

    public function getSalaryGradeStepAttribute()
    {
        return $this->plantillaItem?->salary_grade.'/'.$this->plantillaItem?->step;
    }
}
