<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeFileController extends Controller
{
    public function show(Employee $employee): View
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $employee->load([
            'company',
            'office',
            'department',
            'employmentType',
            'payrollGroup',
            'immediateHead',
            'bank',
            'user',
            'identifiers',
            'certificates',
            'trainings',
            'compensations.compensation',
            'deductions.deduction',
            'loans.loan',
        ]);

        return view('admin.employees.file', [
            'employee' => $employee,
            'company' => $employee->company,
        ]);
    }
}
