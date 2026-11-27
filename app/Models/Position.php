<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Position $model) {
            set_current_timestamp($model);
        });
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function requirements()
    {
        return $this->hasMany(PositionRequirement::class);
    }

    public function jobDescriptions()
    {
        return $this->hasMany(JobDescription::class)->orderByDesc('version');
    }

    public function currentJobDescription()
    {
        return $this->hasOne(JobDescription::class)->where('is_current', true);
    }

    public function assignments()
    {
        return $this->hasMany(PositionAssignment::class);
    }

    public function reportsToPosition()
    {
        return $this->belongsTo(self::class, 'reports_to_position_id');
    }
}
