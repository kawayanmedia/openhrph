<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-employee, per-year ledger used by 13th-month runs, annualisation and
 * BIR 2316 reporting.
 */
class EmployeePayrollYtd extends Model
{
    protected $table = 'employee_payroll_ytd';

    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
