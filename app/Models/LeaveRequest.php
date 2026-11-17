<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [];

    protected $table = 'leave_requests';

    const PENDING = 'Pending';

    const APPROVED = 'Approved';

    const REJECTED = 'Rejected';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date_filed' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (LeaveRequest $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leave()
    {
        return $this->belongsTo(Leave::class, 'leave_type_id');
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

    public function getDateFiledFormatAttribute()
    {
        return $this->date_filed->format('M d, Y');
    }

    public function getDateFromFormatAttribute()
    {
        return $this->date_from->format('M d, Y');
    }

    public function getDateToFormatAttribute()
    {
        return $this->date_to->format('M d, Y');
    }

    public function getLeaveTypeFormatAttribute()
    {
        return $this->leave?->name;
    }

    public function getCoverDatesAttribute()
    {
        return $this->date_from->format('m/d/Y').' to '.$this->date_to->format('m/d/Y');
    }

    public function getAttachmentUrlAttribute()
    {
        if ($this->attachments != null) {
            return url($this->attachments);
        }

        return null;

        return url('storage/'.$this->attachments);
    }
}
