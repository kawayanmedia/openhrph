<?php

namespace App\Models\Government;

use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Government\TrainingType;

class TrainingCourse extends Model
{
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [
        'training_type_id',
        'course_title',
        'course_description',
        'course_est_duration',
        'course_est_cost',
        'course_ave_eval',
    ];

    protected $table = 'training_courses';

    // protected $casts = ['date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (TrainingCourse $model) {
            // set_company($model);
            set_current_timestamp($model);
        });
    }

    public function trainingType()
    {
        return $this->belongsTo(TrainingType::class);
    }

    // public function company()
    // {
    //     return $this->belongsTo(Company::class);
    // }

    // public function employee()
    // {
    //     return $this->belongsTo(Employee::class);
    // }
}
