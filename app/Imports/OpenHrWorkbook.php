<?php

namespace App\Imports;

use App\Imports\Sheets\DepartmentsSheet;
use App\Imports\Sheets\EmployeesSheet;
use App\Imports\Sheets\OfficesSheet;
use App\Imports\Sheets\PayrollGroupsSheet;
use App\Imports\Sheets\PositionsSheet;
use App\Imports\Sheets\SchedulesSheet;
use App\Imports\Workbook\WorkbookDefinition;
use App\Models\EmploymentType;

/**
 * The sheets users can fill in one workbook. Order matters: referenced records
 * are created before the rows that point at them.
 */
class OpenHrWorkbook extends WorkbookDefinition
{
    public function sheets(): array
    {
        return [
            new OfficesSheet,
            new DepartmentsSheet,
            new PayrollGroupsSheet,
            new PositionsSheet,
            new SchedulesSheet,
            new EmployeesSheet,
        ];
    }

    public function externalReferences(): array
    {
        return [
            'employment_type' => ['model' => EmploymentType::class, 'name' => 'name', 'code' => 'code'],
        ];
    }
}
