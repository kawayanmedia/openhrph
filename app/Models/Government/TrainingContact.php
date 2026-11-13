<?php

namespace App\Models\Government;

use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Government\TrainingContactType;

class TrainingContact extends Model
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

    protected $table = 'training_contacts';

    // protected $casts = ['date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (TrainingContact $model) {
            // set_company($model);
            set_current_timestamp($model);
        });
    }

    public function trainingContactType()
    {
        return $this->belongsTo(TrainingContactType::class, 'contact_type_id');
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
