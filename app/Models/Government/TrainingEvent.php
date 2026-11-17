<?php

namespace App\Models\Government;

use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Government\TrainingCourse;
use App\Models\Government\TrainingContact;
use App\Models\Government\TrainingContactType;

class TrainingEvent extends Model
{
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [
        'contact_type_id',
        'contact_name',
        'contact_address',
        'contact_city',
        'contact_phone',
        'contact_fax',
        'contact_email',
        'contact_specialty',
        'contact_eval',
        'remarks',
    ];

    protected $table = 'training_events';

    // protected $casts = ['date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (TrainingEvent $model) {
            // set_company($model);
            set_current_timestamp($model);
        });
    }

    public function trainingCourse()
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function trainingContact()
    {
        return $this->belongsTo(TrainingContact::class, 'contact_id');
    }

    public function trainingContactType()
    {
        return $this->belongsTo(TrainingContactType::class, 'contact_type_id');
    }

    // public function company()
    // {
    //     return $this->belongsTo(Company::class);
    // }
}
