<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAllowanceRecurring extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    const STATUS_DISBURSED = 'disbursed';

    const STATUS_NOT_RELEASE = 'not_release';

    protected $table = 'employees_allowances_recurrings';

    protected $casts = [
        'release_date' => 'date',
    ];

    protected $fillable = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (EmployeeAllowanceRecurring $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function allowance()
    {
        return $this->belongsTo(Allowance::class);
    }

    public function employeeAllowance()
    {
        return $this->belongsTo(EmployeeAllowance::class);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }
}
