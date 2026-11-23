<?php

use App\Imports\Workbook\Exceptions\ImportValidationException;
use App\Imports\Workbook\SampleWorkbook;
use App\Imports\Workbook\WorkbookImporter;
use App\Livewire\Admin\Imports\DataImport;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Office;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/**
 * @return array{0: Company, 1: User}
 */
function importTenant(): array
{
    $company = Company::factory()->create();
    $owner = User::factory()->owner()->forCompany($company)->create();

    app(TenantContext::class)->set((int) $company->id);

    EmploymentType::factory()->create(['name' => 'Regular', 'code' => 'REG']);

    return [$company, $owner];
}

/**
 * @param  array<string, array<int, array<int, string>>>  $sheets
 */
function importWorkbookBytes(array $sheets): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach ($sheets as $title => $rows) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        foreach (array_values($rows) as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, $rowIndex + 1],
                    (string) $value,
                    DataType::TYPE_STRING,
                );
            }
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'import-test-');

    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    $bytes = (string) file_get_contents($path);
    @unlink($path);

    return $bytes;
}

/**
 * @param  array<string, array<int, array<int, string>>>  $sheets
 */
function importWorkbookFile(array $sheets): string
{
    $path = tempnam(sys_get_temp_dir(), 'import-test-');

    file_put_contents($path, importWorkbookBytes($sheets));

    return $path;
}

/**
 * @return array<string, array<int, array<int, string>>>
 */
function validImportSheets(): array
{
    return [
        'Offices' => [
            ['Name', 'Code', 'Account Number'],
            ['Head Office', 'HO', '0012-3456-78'],
        ],
        'Departments' => [
            ['Name', 'Code', 'Address'],
            ['Finance', 'FIN', 'Head Office'],
        ],
        'Payroll Groups' => [
            ['Name', 'Frequency', 'Days per Year', 'Hours per Day', 'Default', 'First Cut-off', 'First Pay Date'],
            ['Monthly', 'monthly', '261', '8', 'Yes', '25', '30'],
        ],
        'Positions' => [
            ['Name', 'Code', 'Department', 'Basic Pay', 'Vacant'],
            ['Accountant', 'ACC', 'Finance', '45000', 'No'],
        ],
        'Schedules' => [
            ['Name', 'Time In', 'Break Start', 'Break End', 'Time Out', 'Default', 'Active'],
            ['Day Shift', '08:00', '12:00', '13:00', '17:00', 'Yes', 'Yes'],
        ],
        'Employees' => [
            [
                'Employee Number', 'Last Name', 'First Name', 'Middle Name', 'Office', 'Department',
                'Employment Type', 'Payroll Group', 'Position', 'Date Hired', 'Payment Type', 'Basic Salary',
                'Minimum Wage', 'Biometric ID', 'TIN', 'SSS Number', 'GSIS BP Number', 'PhilHealth Number',
                'HDMF Number', 'Salary Grade', 'Step', 'Item Number',
            ],
            [
                'EMP-0001', 'Doe', 'Jane', 'Q', 'Head Office', 'Finance', 'Regular', 'Monthly',
                'Accountant', '2026-01-15', 'monthly', '50000', 'No', '1001', '', '', '', '', '', '', '', '',
            ],
        ],
    ];
}

it('imports every sheet of one workbook in dependency order', function () {
    [$company] = importTenant();

    $path = importWorkbookFile(validImportSheets());

    try {
        $report = app(WorkbookImporter::class)->import($path, 'xlsx');
    } finally {
        @unlink($path);
    }

    expect($report->created())->toBe(6)
        ->and($report->updated())->toBe(0);

    $office = Office::query()->where('name', 'Head Office')->firstOrFail();
    $employee = Employee::query()->where('employee_number', 'EMP-0001')->firstOrFail();

    expect($employee->office_id)->toBe($office->id)
        ->and($employee->department_id)->not->toBeNull()
        ->and($employee->employment_type_id)->not->toBeNull()
        ->and($employee->payroll_group_id)->not->toBeNull()
        ->and($employee->schedule_id)->not->toBeNull()
        ->and($employee->payment_type)->toBe(Employee::PAYMENT_TYPE_MONTHLY)
        ->and((int) $employee->basic_salary)->toBe(50000);
});

it('imports the full payroll group details and generates payroll dates', function () {
    [$company] = importTenant();

    $path = importWorkbookFile([
        'Payroll Groups' => [
            [
                'Name', 'Frequency', 'Days per Year', 'Hours per Day', 'Default', 'Compute Tardiness',
                'Compute Overbreak', 'Compute Undertime', 'First Cut-off', 'First Pay Date', 'Second Cut-off',
                'Second Pay Date', 'Pay Date on Non-Working Day', 'SSS Schedule', 'PhilHealth Schedule', 'HDMF Schedule',
            ],
            [
                'Semi-Monthly', 'semi-monthly', '261', '8', 'Yes', 'Yes', 'No', 'Yes', '10', '15', '25', '30',
                'Pay date before non working day', 'Last Pay', 'Every Pay', 'First Pay',
            ],
        ],
    ]);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');
    } finally {
        @unlink($path);
    }

    $group = PayrollGroup::query()->where('name', 'Semi-Monthly')->firstOrFail();

    expect((int) $group->days_year)->toBe(261)
        ->and((int) $group->hours_day)->toBe(8)
        ->and($group->frequency)->toBe(PayrollGroup::FREQUENCY_SEMI_MONTHLY)
        ->and((int) $group->first_cut_off)->toBe(10)
        ->and((int) $group->first_pay_date)->toBe(15)
        ->and((int) $group->second_cut_off)->toBe(25)
        ->and((int) $group->second_pay_date)->toBe(30)
        ->and($group->pay_date_non_working)->toBe(PayrollGroup::NON_WORKING_DATE_BEFORE)
        ->and((bool) $group->is_default)->toBeTrue()
        ->and((bool) $group->compute_tardiness)->toBeTrue()
        ->and((bool) $group->compute_overbreak)->toBeFalse()
        ->and((bool) $group->compute_undertime)->toBeTrue();

    $this->assertDatabaseHas('payroll_groups', [
        'id' => $group->id,
        'hdmf_schedule' => PayrollGroup::PAY_SCHEDULE_FIRSTPAY,
        'sss_schedule' => PayrollGroup::PAY_SCHEDULE_LASTPAY,
    ]);

    $this->assertDatabaseHas('payroll_dates', ['payroll_group_id' => $group->id]);
});

it('rejects a monthly payroll group that provides a second cut-off', function () {
    [$company] = importTenant();

    $path = importWorkbookFile([
        'Payroll Groups' => [
            ['Name', 'Frequency', 'First Cut-off', 'First Pay Date', 'Second Cut-off', 'Second Pay Date'],
            ['Monthly', 'monthly', '25', '30', '15', '30'],
        ],
    ]);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');

        $this->fail('The import should have been rejected.');
    } catch (ImportValidationException $exception) {
        $problems = collect($exception->toArray());
    } finally {
        @unlink($path);
    }

    expect($problems->pluck('message')->implode(' '))->toContain('Monthly payroll groups do not use a second cut-off');
});

it('imports nothing when a single cell is wrong', function () {
    [$company] = importTenant();

    $sheets = validImportSheets();
    $sheets['Employees'][1][4] = 'Missing Office';

    $path = importWorkbookFile($sheets);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');

        $this->fail('The import should have been rejected.');
    } catch (ImportValidationException $exception) {
        $problems = $exception->toArray();
    } finally {
        @unlink($path);
    }

    expect($problems)->toHaveCount(1)
        ->and($problems[0]['sheet'])->toBe('Employees')
        ->and($problems[0]['row'])->toBe(2)
        ->and($problems[0]['column'])->toBe('E')
        ->and($problems[0]['heading'])->toBe('Office')
        ->and($problems[0]['message'])->toContain('was not found in the Offices sheet');

    expect(Office::withoutTenant()->count())->toBe(0)
        ->and(Employee::withoutTenant()->count())->toBe(0);
});

it('reports missing, unknown and duplicate columns and unknown sheets', function () {
    [$company] = importTenant();

    $sheets = [
        'Positions' => [
            ['Name', 'Basic Pay', 'Basic Pay'],
            ['Accountant', '45000', '50000'],
        ],
        'Mystery' => [
            ['Something'],
            ['value'],
        ],
    ];

    $path = importWorkbookFile($sheets);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');

        $this->fail('The import should have been rejected.');
    } catch (ImportValidationException $exception) {
        $problems = collect($exception->toArray());
    } finally {
        @unlink($path);
    }

    expect($problems->pluck('message')->implode(' '))
        ->toContain('Required column "Code" is missing')
        ->toContain('is used more than once')
        ->toContain('is not part of the import template');

    expect($problems->firstWhere('sheet', 'Positions')['row'])->toBe(1);
});

it('rejects duplicate rows inside a sheet', function () {
    [$company] = importTenant();

    $sheets = [
        'Offices' => [
            ['Name', 'Code'],
            ['Head Office', 'HO'],
            ['Head Office', 'HO2'],
        ],
    ];

    $path = importWorkbookFile($sheets);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');

        $this->fail('The import should have been rejected.');
    } catch (ImportValidationException $exception) {
        $problems = collect($exception->toArray());
    } finally {
        @unlink($path);
    }

    expect($problems)->toHaveCount(1)
        ->and($problems->first()['row'])->toBe(3)
        ->and($problems->first()['message'])->toContain('Duplicates row 2');
});

it('rejects formulas and invalid booleans with a clear cell location', function () {
    [$company] = importTenant();

    $sheets = validImportSheets();
    $sheets['Employees'][1][11] = '=50000*12';
    $sheets['Employees'][1][12] = 'Maybe';

    $path = importWorkbookFile($sheets);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');

        $this->fail('The import should have been rejected.');
    } catch (ImportValidationException $exception) {
        $problems = collect($exception->toArray());
    } finally {
        @unlink($path);
    }

    expect($problems->pluck('message')->implode(' '))
        ->toContain('contains a formula')
        ->toContain('must be Yes or No');

    expect($problems->pluck('column')->all())->toBe(['L', 'M']);
});

it('updates existing records by their key and leaves blank cells untouched', function () {
    [$company] = importTenant();

    $first = importWorkbookFile(validImportSheets());
    app(WorkbookImporter::class)->import($first, 'xlsx');
    @unlink($first);

    $second = [
        'Offices' => [
            ['Name', 'Code'],
            ['Head Office', 'HO-NEW'],
        ],
        'Employees' => [
            ['Employee Number', 'Last Name', 'First Name', 'Middle Name', 'Office', 'Department', 'Employment Type', 'Payment Type', 'Basic Salary'],
            ['EMP-0001', 'Doe', 'Jane', '', 'Head Office', 'Finance', 'Regular', 'monthly', '60000'],
        ],
    ];

    $path = importWorkbookFile($second);

    try {
        $report = app(WorkbookImporter::class)->import($path, 'xlsx');
    } finally {
        @unlink($path);
    }

    expect($report->updated())->toBe(2)
        ->and($report->created())->toBe(0);

    expect(Office::query()->count())->toBe(1)
        ->and(Office::query()->firstOrFail()->code)->toBe('HO-NEW');

    $employee = Employee::query()->where('employee_number', 'EMP-0001')->firstOrFail();

    expect((int) $employee->basic_salary)->toBe(60000)
        ->and($employee->middle_name)->toBe('Q');
});

it('resolves references to records that already exist', function () {
    [$company] = importTenant();

    Office::factory()->create(['name' => 'Makati Branch']);
    Department::create(['name' => 'Operations']);
    EmploymentType::factory()->create(['name' => 'Probationary', 'code' => 'PROB']);

    $sheets = [
        'Employees' => [
            ['Employee Number', 'Last Name', 'First Name', 'Office', 'Department', 'Employment Type', 'Payment Type', 'Basic Salary'],
            ['EMP-2000', 'Cruz', 'Ana', 'Makati Branch', 'Operations', 'Probationary', 'daily', '750'],
        ],
    ];

    $path = importWorkbookFile($sheets);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');
    } finally {
        @unlink($path);
    }

    $employee = Employee::query()->where('employee_number', 'EMP-2000')->firstOrFail();

    expect($employee->office_id)->not->toBeNull()
        ->and($employee->department_id)->not->toBeNull()
        ->and($employee->employment_type_id)->not->toBeNull()
        ->and($employee->payment_type)->toBe(Employee::PAYMENT_TYPE_DAILY);
});

it('rejects the same biometric id on two employees in one sheet', function () {
    [$company] = importTenant();

    $sheets = validImportSheets();
    $second = $sheets['Employees'][1];
    $second[0] = 'EMP-0002';
    $sheets['Employees'][] = $second;

    $path = importWorkbookFile($sheets);

    try {
        app(WorkbookImporter::class)->import($path, 'xlsx');

        $this->fail('The import should have been rejected.');
    } catch (ImportValidationException $exception) {
        $problems = collect($exception->toArray());
    } finally {
        @unlink($path);
    }

    expect($problems->pluck('message')->implode(' '))->toContain('Biometric ID must be unique');
});

it('shows the problems on the import page and writes nothing', function () {
    Storage::fake('local');

    [$company, $owner] = importTenant();

    $sheets = validImportSheets();
    $sheets['Employees'][1][4] = 'Missing Office';

    $component = Livewire::actingAs($owner)
        ->test(DataImport::class)
        ->set('file', UploadedFile::fake()->createWithContent('workbook.xlsx', importWorkbookBytes($sheets)))
        ->call('import');

    expect($component->get('problems'))->toHaveCount(1)
        ->and($component->get('problems')[0]['row'])->toBe(2)
        ->and($component->get('summary'))->toBe([]);

    expect(Office::withoutTenant()->count())->toBe(0);
});

it('imports a valid workbook from the import page', function () {
    Storage::fake('local');

    [$company, $owner] = importTenant();

    $component = Livewire::actingAs($owner)
        ->test(DataImport::class)
        ->set('file', UploadedFile::fake()->createWithContent('workbook.xlsx', importWorkbookBytes(validImportSheets())))
        ->call('import');

    expect($component->get('problems'))->toBe([])
        ->and($component->get('summary'))->toHaveKey('Employees');

    expect(Employee::query()->where('employee_number', 'EMP-0001')->exists())->toBeTrue();
});

it('imports the generated sample workbook without any problems', function () {
    [$company] = importTenant();

    $spreadsheet = app(SampleWorkbook::class)->build(true);

    $path = tempnam(sys_get_temp_dir(), 'sample-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    try {
        $report = app(WorkbookImporter::class)->import($path, 'xlsx');
    } finally {
        @unlink($path);
    }

    expect($report->total())->toBeGreaterThan(0)
        ->and(Employee::query()->where('employee_number', 'EMP-0001')->exists())->toBeTrue();
});

it('generates a blank template with every sheet and the exact headings', function () {
    $spreadsheet = app(SampleWorkbook::class)->build(false);

    expect($spreadsheet->getSheetNames())->toContain(
        'Instructions',
        'Offices',
        'Departments',
        'Payroll Groups',
        'Positions',
        'Schedules',
        'Employees',
    )->not->toContain(
        'Employment Types',
        'Deductions',
        'Compensations',
        'Leave Types',
        'Rates',
        'Holidays',
    );

    $employees = $spreadsheet->getSheetByName('Employees');

    expect($employees->getCell('A1')->getValue())->toBe('Employee Number *')
        ->and($employees->getCell('B1')->getValue())->toBe('Last Name *')
        ->and($employees->getCell('D1')->getValue())->toBe('Middle Name');
});

it('gates the import page and the template download', function () {
    $this->get('/app/imports')->assertRedirect(route('admin.login'));
    $this->get('/app/imports/template?sample=1')->assertRedirect(route('admin.login'));

    [$company, $owner] = importTenant();

    $this->actingAs($owner)->get('/app/imports')->assertOk();

    $response = $this->actingAs($owner)->get('/app/imports/template?sample=1');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
});
