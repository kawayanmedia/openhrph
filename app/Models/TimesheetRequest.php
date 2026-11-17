<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [];

    protected $table = 'timesheet_requests';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'log_date' => 'date',
        'date_filed' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (TimesheetRequest $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employeeApproved()
    {
        return $this->belongsTo(Employee::class, 'approved_user_id');
    }

    public function scopeByStatus($query, $status)
    {
        if ($status != null) {
            return $query->where('status', $status);
        }
    }

    public function getDayTypeFormatAttribute()
    {
        // We need to check also if restday for employee
        if ($this->day_type == null) {
            return 'Regular, Non-Holiday';
        }

        // return 'Rest Day, Non-Holiday';
        return $this->day_type;
    }
}
