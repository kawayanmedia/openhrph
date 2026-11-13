<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAllowance extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $table = 'employees_allowances';

    protected $casts = [
        'release_date' => 'date',
        'valid_date_from' => 'date',
        'valid_date_to' => 'date',
    ];

    protected $fillable = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (EmployeeAllowance $model) {
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

    public function employeeAllowanceRecurrings()
    {
        return $this->hasMany(EmployeeAllowanceRecurring::class);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }
}
