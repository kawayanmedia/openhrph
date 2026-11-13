<?php

namespace App\Models\Government;

use App\Models\Employee;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRecord extends Model
{
    use FilterScope;
    use SoftDeletes;

    protected $fillable = ['date_from', 'date_to', 'designation'];

    protected $table = 'service_records';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        // static::created(function (Office $model) {
        //     set_company($model);
        //     set_current_timestamp($model);
        // });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
