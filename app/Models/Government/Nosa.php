<?php

namespace App\Models\Government;

use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Government\PlantillaItem;
use App\Models\Employee;
use App\Models\Office;

class Nosa extends Model
{
    use FilterScope;
    use SoftDeletes;

    protected $table = 'nosas';

    protected $appends = ['office_name'];

    protected $fillable = [
        'office_id',
        'employee_id',
        'item_number',
        'from_salary_grade',
        'from_step',
        'from_salary_grade_year',
        'from_monthly_salary',
        'to_salary_grade',
        'to_step',
        'to_salary_grade_year',
        'to_monthly_salary',
        'effective_date',
    ];

    protected $casts = ['effective_date' => 'date'];


    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Nosa $model) {
            set_current_timestamp($model);
        });
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class);
    }

    public function getOfficeNameAttribute()
    {
        return $this->office?->name;
    }


    public function getSalaryGradeStepAttribute()
    {
        return $this->salary_grade . '/' . $this->step;
    }
}
