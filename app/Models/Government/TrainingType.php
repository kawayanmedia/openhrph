<?php

namespace App\Models\Government;

use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingType extends Model
{
    use FilterScope;
    use SoftDeletes;

    protected $fillable = ['training_type_code', 'training_type', 'training_type_desc'];

    protected $table = 'training_types';

    // protected $casts = ['date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (TrainingType $model) {
            // set_company($model);
            set_current_timestamp($model);
        });
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
