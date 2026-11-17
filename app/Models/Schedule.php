<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;

    protected $casts = [
        'effective_date_start' => 'date',
        'effective_date_end' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Schedule $model) {
            set_current_timestamp($model);
        });
    }

    public function employeeSchedules()
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function getTimeInAttribute()
    {
        return $this->am_in ?? $this->pm_in;
    }

    public function getTimeOutAttribute()
    {
        return $this->pm_out ?? $this->am_out ?? $this->am_out_morning;
    }

    public function getOutMorningAttribute()
    {
        return $this->am_out_morning;
    }

    public function getTimeInFormatAttribute()
    {
        if ($this->am_in and $this->pm_out) {
            return convert_time($this->am_in).' - '.convert_time($this->pm_out);
        }

        if ($this->pm_in and $this->pm_out) {
            return convert_time($this->pm_in).' - '.convert_time($this->pm_out);
        }

        if ($this->pm_in and $this->am_out_morning) {
            return convert_time($this->pm_in).' - '.convert_time($this->am_out_morning);
        }
    }

    public function getBreakTimeAttribute()
    {
        if ($this->break_time_start and $this->break_time_end) {
            return convert_time($this->break_time_start).' - '.convert_time($this->break_time_end);
        }
    }

    public function updateTime($request, Model $model)
    {
        $inPm = false;
        $outAm = false;

        if ($request->time_in > '12:00') {
            $model->pm_in = $request->time_in;
            $inPm = true;
        } else {
            $model->am_in = $request->time_in;
        }

        if ($request->time_out > '12:00') {
            $model->pm_out = $request->time_out;
        } else {
            $model->am_out = $request->time_out;
            $outAm = true;
        }

        if ($inPm and $outAm) {
            $model->am_out_morning = $request->time_out;
        }

        if ($model->is_default) {
            static::where('id', '!=', $model->id)->update(['is_default' => false]);
        }

        $model->save();
    }
}
