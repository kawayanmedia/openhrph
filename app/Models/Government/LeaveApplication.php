<?php

namespace App\Models\Government;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveApplication extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'account_number'];

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

    public function getLeaveDateAttribute()
    {
        return 'Leave Date';
    }
}
