<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCompensation extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    const STATUS_DISBURSED = 'disbursed';

    const STATUS_NOT_RELEASE = 'not_release';

    const STATUS_CANCELLED = 'cancelled';

    protected $table = 'employees_compensations';

    protected $casts = [
        'release_date' => 'date',
        'valid_date_from' => 'date',
        'valid_date_to' => 'date',
    ];

    protected $fillable = [
        'employee_id',
        'compensation_id',
        'amount',
        'status',
        'release_date',
        'is_special',
        'is_active',
        'is_never_expires',
    ];

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

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOfGroup(Builder $query, string $group): void
    {
        $query->whereHas('compensation', fn (Builder $query): Builder => $query->where('group', $group));
    }

    public function scopeNotYetReleased(Builder $query, ?string $releasedDate = null): void
    {
        if ($releasedDate !== null) {
            $query->where('release_date', $releasedDate);
        }

        $query->where('status', '!=', self::STATUS_DISBURSED);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : $value / 100,
            set: fn ($value) => $value === null ? null : $value * 100
        );
    }

    public function getIsDisbursedAttribute(): bool
    {
        return $this->status === self::STATUS_DISBURSED;
    }

    public function getStatusFormatAttribute(): ?string
    {
        return match ($this->status) {
            self::STATUS_DISBURSED => 'Disbursed',
            self::STATUS_NOT_RELEASE => 'Not yet release',
            self::STATUS_CANCELLED => 'Cancelled',
            default => null,
        };
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
