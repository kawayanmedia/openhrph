<?php

namespace App\Models\Government;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use App\Models\Office;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlantillaItem extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'plantilla_items';

    protected $appends = ['office_name'];

    protected $fillable = [
        'office_id',
        'employee_id',
        'item_number',
        'position_title',
        'salary_grade',
        'step',
        'have_budget',
        'is_vacant',
        'annual_salary_authorized',
        'annual_salary_actual',
    ];

    protected $casts = [
        'is_vacant' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (PlantillaItem $model) {
            set_current_timestamp($model);
        });

        static::updated(function (PlantillaItem $model) {
            // Update or create Employee plantilla
            if ($model->employee_id != null) {
                $plantilla = EmployeePlantilla::firstOrNew([
                    'office_id' => $model->office_id,
                    'employee_id' => $model->employee_id,
                    'plantilla_item_id' => $model->id,
                    'appointment_date' => $model->appointment_date,
                ]);
                $plantilla->save();
            }
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

    public function getOfficeNameAttribute()
    {
        return $this->office?->name;
    }

    public function getIncubentNameAttribute()
    {
        return $this->employee?->fullname ?? 'Vacant';
    }

    public function getSalaryGradeStepAttribute()
    {
        return $this->salary_grade.'/'.$this->step;
    }

    public function annualSalaryAuthorized(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function annualSalaryActual(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }
}
