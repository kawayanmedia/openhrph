<?php

namespace App\Imports\Sheets;

use App\Imports\Workbook\Column;
use App\Imports\Workbook\ColumnType;
use App\Imports\Workbook\ReferenceIndex;
use App\Imports\Workbook\SheetDefinition;
use App\Models\Employee as EmployeeModel;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeesSheet extends SheetDefinition
{
    public function key(): string
    {
        return 'employees';
    }

    public function title(): string
    {
        return 'Employees';
    }

    public function aliases(): array
    {
        return ['Employee'];
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return EmployeeModel::class;
    }

    public function keyColumns(): array
    {
        return ['employee_number'];
    }

    public function secondaryUniqueColumns(): array
    {
        return ['biometric_id'];
    }

    public function referenceColumns(): array
    {
        return [
            'office' => 'office',
            'department' => 'department',
            'employment_type' => 'employment_type',
            'payroll_group' => 'payroll_group',
            'position' => 'position',
        ];
    }

    public function warnings(array $values, ReferenceIndex $references, bool $creating): array
    {
        $warnings = [];
        $position = $values['position'] ?? null;

        if ($position !== null && $references->status('position', (string) $position) === null) {
            $warnings[] = sprintf(
                'Position "%s" does not match a Positions record; it will be stored as a free-text title.',
                $position,
            );
        }

        if ($creating && ! isset($values['payroll_group'])) {
            $warnings[] = 'No payroll group set; assign one before running payroll.';
        }

        return $warnings;
    }

    public function columns(): array
    {
        return [
            new Column(
                key: 'employee_number',
                heading: 'Employee Number',
                type: ColumnType::Text,
                required: true,
                rules: ['max:10'],
                aliases: ['Employee No', 'Emp No'],
                example: 'EMP-0001',
                help: 'Unique per company. An existing number updates that employee.',
            ),
            new Column(
                key: 'last_name',
                heading: 'Last Name',
                type: ColumnType::Text,
                required: true,
                rules: ['max:64'],
                example: 'Sample',
            ),
            new Column(
                key: 'first_name',
                heading: 'First Name',
                type: ColumnType::Text,
                required: true,
                rules: ['max:64'],
                example: 'Employee',
            ),
            new Column(
                key: 'middle_name',
                heading: 'Middle Name',
                type: ColumnType::Text,
                rules: ['max:64'],
            ),
            new Column(
                key: 'office',
                heading: 'Office',
                type: ColumnType::Text,
                required: true,
                rules: ['max:128'],
                aliases: ['Office Branch'],
                example: 'Head Office',
                help: 'Must match an office in the Offices sheet or an existing office.',
            ),
            new Column(
                key: 'department',
                heading: 'Department',
                type: ColumnType::Text,
                required: true,
                rules: ['max:128'],
                example: 'Finance',
                help: 'Must match a department in the Departments sheet or an existing department.',
            ),
            new Column(
                key: 'employment_type',
                heading: 'Employment Type',
                type: ColumnType::Text,
                required: true,
                rules: ['max:128'],
                example: 'Regular',
                help: 'Must match an existing employment type.',
            ),
            new Column(
                key: 'payroll_group',
                heading: 'Payroll Group',
                type: ColumnType::Text,
                rules: ['max:32'],
                example: 'Monthly',
                help: 'Optional. Must match an existing payroll group or one in the Payroll Groups sheet.',
            ),
            new Column(
                key: 'position',
                heading: 'Position',
                type: ColumnType::Text,
                rules: ['max:64'],
                example: 'Accountant',
                help: 'Optional job title. Links to the Positions sheet when the name matches.',
            ),
            new Column(
                key: 'date_hired',
                heading: 'Date Hired',
                type: ColumnType::Date,
                rules: ['date_format:Y-m-d'],
                example: '2026-01-15',
                help: 'Written as YYYY-MM-DD.',
            ),
            new Column(
                key: 'payment_type',
                heading: 'Payment Type',
                type: ColumnType::Text,
                required: true,
                rules: ['max:32'],
                options: ['monthly', 'daily'],
                example: 'monthly',
                help: 'monthly or daily.',
            ),
            new Column(
                key: 'basic_salary',
                heading: 'Basic Salary',
                type: ColumnType::Decimal,
                required: true,
                rules: ['numeric', 'min:0'],
                example: '50000',
                help: 'Monthly salary, or the daily rate when the payment type is daily.',
            ),
            new Column(
                key: 'is_minimum_wage',
                heading: 'Minimum Wage',
                type: ColumnType::Boolean,
                aliases: ['Is Minimum Wage'],
                options: ['Yes', 'No'],
                example: 'No',
            ),
            new Column(
                key: 'biometric_id',
                heading: 'Biometric ID',
                type: ColumnType::Text,
                rules: ['max:32'],
                example: '1001',
                help: 'The employee number on the biometric device, if any.',
            ),
            new Column(
                key: 'tin_number',
                heading: 'TIN',
                type: ColumnType::Text,
                rules: ['regex:/^\d{3}-?\d{3}-?\d{3}(-?\d{3,5})?$/'],
                messages: ['regex' => 'The TIN must look like 000-000-000-000.'],
                aliases: ['TIN Number'],
                example: '123-456-789-000',
            ),
            new Column(
                key: 'sss_number',
                heading: 'SSS Number',
                type: ColumnType::Text,
                rules: ['regex:/^\d{2}-?\d{7}-?\d$/'],
                messages: ['regex' => 'The SSS number must look like 00-0000000-0.'],
                aliases: ['SSS', 'SSS No'],
                example: '11-1234567-8',
            ),
            new Column(
                key: 'gsis_number',
                heading: 'GSIS BP Number',
                type: ColumnType::Text,
                rules: ['regex:/^[\d\s-]{8,24}$/'],
                messages: ['regex' => 'The GSIS BP number must be 8 to 24 digits, spaces or dashes.'],
                aliases: ['GSIS', 'GSIS No'],
                example: '0123456789012',
            ),
            new Column(
                key: 'philhealth_number',
                heading: 'PhilHealth Number',
                type: ColumnType::Text,
                rules: ['regex:/^\d{2}-?\d{9}-?\d$/'],
                messages: ['regex' => 'The PhilHealth number must look like 00-000000000-0.'],
                aliases: ['PhilHealth', 'PhilHealth No'],
                example: '12-345678901-2',
            ),
            new Column(
                key: 'hdmf_number',
                heading: 'HDMF Number',
                type: ColumnType::Text,
                rules: ['regex:/^\d{4}-?\d{4}-?\d{4}$/'],
                messages: ['regex' => 'The HDMF number must look like 0000-0000-0000.'],
                aliases: ['HDMF', 'Pag-IBIG', 'Pag-IBIG Number'],
                example: '1234-5678-9012',
            ),
            new Column(
                key: 'salary_grade',
                heading: 'Salary Grade',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,33'],
                example: '15',
            ),
            new Column(
                key: 'step',
                heading: 'Step',
                type: ColumnType::Integer,
                rules: ['integer', 'between:1,8'],
                example: '1',
            ),
            new Column(
                key: 'item_number',
                heading: 'Item Number',
                type: ColumnType::Text,
                rules: ['max:64'],
                example: 'ITEM-0001',
            ),
        ];
    }

    public function sampleRows(): array
    {
        return [
            [
                'employee_number' => 'EMP-0001',
                'last_name' => 'Sample',
                'first_name' => 'Employee',
                'middle_name' => 'Data',
                'office' => 'Head Office',
                'department' => 'Finance',
                'employment_type' => 'Regular',
                'payroll_group' => 'Semi-Monthly',
                'position' => 'Accountant',
                'date_hired' => '2026-01-15',
                'payment_type' => 'monthly',
                'basic_salary' => '50000',
                'is_minimum_wage' => 'No',
                'biometric_id' => '1001',
                'tin_number' => '123-456-789-000',
                'sss_number' => '11-1234567-8',
                'philhealth_number' => '12-345678901-2',
                'hdmf_number' => '1234-5678-9012',
            ],
        ];
    }

    public function validateValues(array $values, int $row, callable $add): void
    {
        $paymentType = $this->paymentType((string) $values['payment_type']);

        if ($paymentType === null) {
            $add('payment_type', 'Payment Type must be monthly or daily.');
        }

        $biometricId = $values['biometric_id'] ?? null;

        if ($biometricId !== null && $biometricId !== '') {
            $conflict = EmployeeModel::query()
                ->where('biometric_id', (string) $biometricId)
                ->whereRaw('UPPER(TRIM(employee_number)) != ?', [
                    Str::upper(trim((string) ($values['employee_number'] ?? ''))),
                ])
                ->exists();

            if ($conflict) {
                $add('biometric_id', 'This biometric ID is already used by another employee.');
            }
        }
    }

    public function validateReferences(array $values, int $row, ReferenceIndex $references, callable $add): void
    {
        $referencesToCheck = [
            ['office', 'office', 'Office', 'was not found in the Offices sheet or in your company offices.'],
            ['department', 'department', 'Department', 'was not found in the Departments sheet or in your company departments.'],
            ['employment_type', 'employment_type', 'Employment type', 'was not found. Create it under Settings → Employment Type first.'],
            ['payroll_group', 'payroll_group', 'Payroll group', 'was not found in the Payroll Groups sheet or in your company payroll groups.'],
        ];

        foreach ($referencesToCheck as [$column, $type, $label, $suffix]) {
            $value = $values[$column] ?? null;

            if ($value === null || $references->exists($type, (string) $value)) {
                continue;
            }

            $add($column, sprintf('%s "%s" %s', $label, $value, $suffix));
        }
    }

    public function fill(Model $model, array $values, ReferenceIndex $references, bool $creating): void
    {
        if ($creating) {
            $model->setAttribute('is_minimum_wage', false);
            $model->setAttribute('is_monday_friday', true);

            $defaultSchedule = Schedule::query()->where('is_default', true)->value('id');

            if ($defaultSchedule !== null) {
                $model->setAttribute('schedule_id', $defaultSchedule);
            }
        }

        $this->setValues($model, $values, [
            'employee_number' => 'employee_number',
            'last_name' => 'last_name',
            'first_name' => 'first_name',
            'middle_name' => 'middle_name',
            'position' => 'position',
            'date_hired' => 'date_hired',
            'is_minimum_wage' => 'is_minimum_wage',
            'biometric_id' => 'biometric_id',
            'tin_number' => 'tin_number',
            'sss_number' => 'sss_number',
            'gsis_number' => 'gsis_number',
            'philhealth_number' => 'philhealth_number',
            'hdmf_number' => 'hdmf_number',
            'salary_grade' => 'salary_grade',
            'step' => 'step',
            'item_number' => 'item_number',
        ]);

        $paymentType = $this->paymentType((string) $values['payment_type']);

        if ($paymentType !== null) {
            $model->setAttribute('payment_type', $paymentType);
        }

        $model->setAttribute('basic_salary', (int) round((float) $values['basic_salary']));

        foreach ([
            'office' => 'office_id',
            'department' => 'department_id',
            'employment_type' => 'employment_type_id',
            'payroll_group' => 'payroll_group_id',
        ] as $column => $attribute) {
            $value = $values[$column] ?? null;
            $id = $value === null ? null : $references->id($column, (string) $value);

            if ($id !== null) {
                $model->setAttribute($attribute, $id);
            }
        }

        $position = $values['position'] ?? null;
        $positionId = $position === null ? null : $references->id('position', (string) $position);

        if ($positionId !== null) {
            $model->setAttribute('position_id', $positionId);
        }
    }

    private function paymentType(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'monthly', 'monthly paid' => EmployeeModel::PAYMENT_TYPE_MONTHLY,
            'daily', 'daily paid' => EmployeeModel::PAYMENT_TYPE_DAILY,
            default => null,
        };
    }
}
