<?php

namespace App\Livewire\Admin\Dashboards;

use App\Livewire\Admin\Metrics\ChangePasswordMessage;
use App\Livewire\Admin\Metrics\EmploymentType;
use App\Livewire\Admin\Metrics\HolidaysTable;
use App\Livewire\Admin\Metrics\MonthlyPayrollTrend;
use App\Livewire\Admin\Metrics\NewAttendanceApplications;
use App\Livewire\Admin\Metrics\NewLeaveApplications;
use App\Livewire\Admin\Metrics\NewOvertimeApplications;
use App\Livewire\Admin\Metrics\NewPayroll;
use App\Livewire\Admin\Metrics\NewScheduleApplications;
use App\Livewire\Admin\Metrics\TotalDepartments;
use App\Livewire\Admin\Metrics\TotalEmployees;
use App\Livewire\Admin\Metrics\TotalOffices;
use App\Livewire\Admin\Metrics\TotalUsers;
use App\Services\Payroll\PayrollReadiness;
use Livewire\Component;

class Main extends Component
{
    public function render()
    {
        $metrics = [
            new TotalEmployees,
            new TotalOffices,
            new TotalDepartments,
            new TotalUsers,
            new NewAttendanceApplications,
            new NewLeaveApplications,
            new NewOvertimeApplications,
            new NewScheduleApplications,
            new NewPayroll,
            new MonthlyPayrollTrend,
            new EmploymentType,
            new HolidaysTable,
            new ChangePasswordMessage,
        ];

        return view('livewire.admin.dashboard', [
            'metrics' => $metrics,
            'readiness' => app(PayrollReadiness::class)->summary(),
        ])->layout('layouts.admin')->title('Dashboard');
    }
}
