<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLoan extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [];

    protected $table = 'employees_loans';

    protected $casts = [
        'approved_date' => 'date',
        'repayment_start_date' => 'date',
        'repayment_end_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (EmployeeLoan $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function balance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function monthlyAmortization(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }
}
