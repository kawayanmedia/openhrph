<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCommission extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    const STATUS_DISBURSED = 'disbursed';

    const STATUS_NOT_RELEASE = 'not_release';

    protected $fillable = [];

    protected $table = 'employees_commissions';

    protected $casts = ['release_date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (EmployeeCommission $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function getIsDisbursedAttribute(): bool
    {
        return $this->status == static::STATUS_DISBURSED;
    }

    public function getStatusFormatAttribute()
    {
        $statuses = [
            'disbursed' => 'Disbursed',
            'not_release' => 'Not yet release',
        ];

        return $statuses[$this->status];
    }
}
