<?php

namespace App\Models\Government;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePlantilla extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'employees_plantilla';

    protected $fillable = [
        'office_id',
        'employee_id',
        'plantilla_item_id',
        'year',
        'active',
        'appointment_date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (EmployeePlantilla $model) {
            set_current_timestamp($model);
        });
    }

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function basicSalary(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }
}
