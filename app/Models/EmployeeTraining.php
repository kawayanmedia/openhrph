<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTraining extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [];

    protected $table = 'employees_trainings';

    protected $casts = ['date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (EmployeeTraining $model) {
            set_current_timestamp($model);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
