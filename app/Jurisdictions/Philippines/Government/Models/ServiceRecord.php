<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Employee;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRecord extends Model
{
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'date_from',
        'date_to',
        'designation',
        'status',
        'salary',
        'office_entity',
        'branch',
        'remarks',
        'lwop',
        'separation_date',
        'separation_cause',
    ];

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
