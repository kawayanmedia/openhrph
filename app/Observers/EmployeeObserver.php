<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\PayrollGroup;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     *
     * @return void
     */
    public function created(Employee $employee)
    {
        $this->updateRecord($employee);
    }

    /**
     * Handle the Employee "updated" event.
     *
     * @return void
     */
    public function updated(Employee $employee)
    {
        $this->updateRecord($employee);
    }

    /**
     * Handle the Employee "deleted" event.
     *
     * @return void
     */
    public function deleted(Employee $employee)
    {
        //
    }

    /**
     * Handle the Employee "restored" event.
     *
     * @return void
     */
    public function restored(Employee $employee)
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Employee $employee)
    {
        //
    }

    public function updateRecord(Employee $employee)
    {
        if ($employee->basic_salary != null) {
            if ($employee->payment_type == Employee::PAYMENT_TYPE_MONTHLY) {
                
                $days = $employee->payrollGroup?->days_year;

                if ($days == null) {
                    $days = PayrollGroup::NUMBER_WORKING_DAYS_MONFRI;
                }
                
                $employee->daily_rate = ($employee->basic_salary * 12) / $days;
                $employee->saveQuietly();
                $employee->hourly_rate = $employee->daily_rate / 8;
                $employee->saveQuietly();
            } elseif ($employee->payment_type == Employee::PAYMENT_TYPE_DAILY) {
                $employee->daily_rate = $employee->basic_salary;
                $employee->saveQuietly();
                $employee->hourly_rate = $employee->daily_rate / 8;
                $employee->saveQuietly();
            }
        }
    }
}
