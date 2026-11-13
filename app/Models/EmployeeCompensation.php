<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCompensation extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $table = 'employees_compensations';

    protected $casts = [
        'release_date' => 'date',
        'valid_date_from' => 'date',
        'valid_date_to' => 'date',
    ];

    protected $fillable = ['is_never_expires'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (EmployeeCompensation $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function compensation()
    {
        return $this->belongsTo(Compensation::class);
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

    public function getIsActiveFormatAttribute()
    {
        // If the valid_date_to is null then lets check if the is_never_expires is true
        if ($this->is_never_expires == true) {
            return (bool) $this->is_never_expires;
        }

        // If the date has value then lets check if the date is valid
        if ($this->valid_date_from && $this->valid_date_to) {
            return $this->valid_date_from <= now() && $this->valid_date_to >= now();
        }

        return false;
    }
}
