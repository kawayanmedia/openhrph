<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficialBusinessRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [];

    protected $table = 'official_business_requests';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date_filed' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',
        'time_from' => 'datetime',
        'time_to' => 'datetime',
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
        static::created(function (OfficialBusinessRequest $model) {
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

    public function getAttachmentUrlAttribute()
    {
        if ($this->attachments != null) {
            return url($this->attachments);
        }

        return null;
    }
}
