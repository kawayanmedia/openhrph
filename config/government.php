<?php

use App\Jurisdictions\Philippines\Government\Models\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Models\ServiceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Office;
use App\Models\PayrollGroup;
use App\Models\Position;
use App\Models\ScheduleAdjustmentRequest;
use App\Models\User;
use KawayanMedia\Government\Models\Applicant;
use KawayanMedia\Government\Models\EmployeePlantilla;
use KawayanMedia\Government\Models\JobVacancy;
use KawayanMedia\Government\Models\Nosa;
use KawayanMedia\Government\Models\SalaryGrade;

return [

    /*
    |--------------------------------------------------------------------------
    | Model map
    |--------------------------------------------------------------------------
    |
    | Government-domain records use the package models. Core entities stay on
    | the application models so the admin panel, tenancy and payroll keep
    | working unchanged. Values are resolved lazily.
    |
    */

    'models' => [
        'applicant' => Applicant::class,
        'employee' => Employee::class,
        'employee_plantilla' => EmployeePlantilla::class,
        'job_vacancy' => JobVacancy::class,
        'nosa' => Nosa::class,
        'office' => Office::class,
        'plantilla_item' => PlantillaItem::class,
        'salary_grade' => SalaryGrade::class,
        'schedule_adjustment_request' => ScheduleAdjustmentRequest::class,
        'service_record' => ServiceRecord::class,
        'user' => User::class,
        'company' => Company::class,
        'department' => Department::class,
        'employment_type' => EmploymentType::class,
        'position' => Position::class,
        'payroll_group' => PayrollGroup::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | NOSA / NOSI PDF generation
    |--------------------------------------------------------------------------
    |
    | The package registers two routes that render the NOSA and NOSI forms.
    | They live inside the admin panel and require an authenticated admin
    | user; set `ADMIN_PATH` to move the panel.
    |
    */

    'pdf' => [
        'route_prefix' => env('ADMIN_PATH', 'app').'/documents',
        'middleware' => ['web', 'auth:web,nova', 'can:viewAdmin'],
        'route_names' => [
            'nosa' => 'government.nosa.show',
            'nosi' => 'government.nosi.show',
        ],
        'templates' => [
            'nosa' => null,
            'nosi' => null,
        ],
    ],

];
