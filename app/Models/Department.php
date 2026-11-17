<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'code', 'account_number', 'address'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Department $model) {
            set_current_timestamp($model);
        });
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function attendanceApprovalEmployee()
    {
        return $this->belongsTo(Employee::class, 'attendance_approval_employee_id');
    }

    public function overtimeApprovalEmployee()
    {
        return $this->belongsTo(Employee::class, 'overtime_approval_employee_id');
    }

    public function leaveApprovalEmployee()
    {
        return $this->belongsTo(Employee::class, 'leave_approval_employee_id');
    }
}
