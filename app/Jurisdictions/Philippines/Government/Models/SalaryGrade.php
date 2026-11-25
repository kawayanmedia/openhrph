<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryGrade extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'salary_grade_type',
        'sg',
        'year',
        'step1',
        'step2',
        'step3',
        'step4',
        'step5',
        'step6',
        'step7',
        'step8',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        // static::created(function (Office $model) {
        //     set_company($model);
        //     set_current_timestamp($model);
        // });
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public static function officesList()
    {
        // return Office::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
