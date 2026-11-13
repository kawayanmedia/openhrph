<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeBonus extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    const STATUS_DISBURSED = 'disbursed';

    const STATUS_NOT_RELEASE = 'not_release';

    protected $fillable = [
        'employee_id',
        'bonus_id',
    ];

    protected $table = 'employees_bonuses';

    protected $casts = [
        'release_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (EmployeeBonus $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function bonus()
    {
        return $this->belongsTo(Bonus::class);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function scopeNotYetReleased(Builder $query, ?string $releasedDate = null): void
    {
        if ($releasedDate != null) {
            $query->where('release_date', $releasedDate);
        }

        $query->where('status', '!=', EmployeeBonus::STATUS_DISBURSED); // Dont include if already disbursed
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
