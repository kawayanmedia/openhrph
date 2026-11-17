<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Office;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TimesheetImport implements ToCollection, WithHeadingRow
{
    /**
     * @param  array  $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    // public function model(array $row)
    // {
    //     return new Employee([
    //         //
    //     ]);
    // }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            // Check if we have office already in db
            // $office = Office::firstOrCreate(['name' => $row['office_branch']]);
            // $deparment = Department::firstOrCreate(['name' => $row['department']]);

            // Employee::create([
            //     'employee_number' => $row['employee_number'],
            //     'last_name' => $row['last_name'],
            //     'first_name' => $row['first_name'],
            //     'middle_name' => $row['middle_name'],
            //     'position' => $row['position'],
            //     'office_id' => $office->id,
            //     'department_id' => $deparment->id,
            //     'biometric_id' => $row['biometric_id'],
            //     'basic_salary' => $row['basic_salary'],
            //     'tin_number' => $row['tin'],
            //     'sss_number' => $row['sss'],
            //     'philhealth_number' => $row['phil_health'],
            //     'hdmf_number' => $row['hdmf'],
            // ]);
        }
    }
}
