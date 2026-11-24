<?php

namespace App\Livewire\Admin\Reports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Office;
use App\Models\PayrollGroup;
use App\Services\Employees\EmployeeMasterFileQuery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class EmployeeMasterFile extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $officeId = null;

    #[Url]
    public ?int $departmentId = null;

    #[Url]
    public ?int $employmentTypeId = null;

    #[Url]
    public ?int $payrollGroupId = null;

    #[Url]
    public ?int $employmentStatus = null;

    #[Url]
    public string $active = '';

    #[Url]
    public string $sort = 'last_name';

    #[Url]
    public string $direction = 'asc';

    #[Url]
    public int $perPage = 25;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, EmployeeMasterFileQuery::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'officeId', 'departmentId', 'employmentTypeId', 'payrollGroupId', 'employmentStatus', 'active']);
        $this->resetPage();
    }

    public function render(EmployeeMasterFileQuery $masterFile)
    {
        $filters = $this->filters();
        $employees = $masterFile->query($filters)
            ->paginate(in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25);

        return view('livewire.admin.reports.employee-master-file', [
            'employees' => $employees,
            'offices' => Office::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::query()->orderBy('name')->get(),
            'payrollGroups' => PayrollGroup::query()->orderBy('name')->get(),
            'statuses' => (new Employee)->statuses(),
            'totalMonthlyBasic' => $employees->getCollection()->sum(fn (Employee $employee): int => (int) $employee->getRawOriginal('basic_salary')),
            'csvUrl' => route('admin.reports.employee-master-file.csv', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')),
        ])->title('Employee Master File');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        return [
            'search' => $this->search,
            'office_id' => $this->officeId,
            'department_id' => $this->departmentId,
            'employment_type_id' => $this->employmentTypeId,
            'payroll_group_id' => $this->payrollGroupId,
            'employment_status' => $this->employmentStatus,
            'active' => $this->active,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }
}
