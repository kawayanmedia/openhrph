<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollDate extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    const STATUS_RUNNING = 'running';

    const FIRST_CUTOFF = 1;

    const SECOND_CUTOFF = 2;

    protected $table = 'payroll_dates';

    protected $fillable = [
        'company_id',
        'payroll_group_id',
        'payroll_date',
    ];

    protected $casts = [
        'payroll_date' => 'date',
        'attendance_date_start' => 'date',
        'attendance_date_end' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (PayrollDate $model) {
            set_current_timestamp($model);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollGroup()
    {
        return $this->belongsTo(PayrollGroup::class);
    }

    public function getIsRunningAttribute(): bool
    {
        return $this->status == static::STATUS_RUNNING;
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->status == static::STATUS_OPEN;
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->status == static::STATUS_CLOSED;
    }
}
