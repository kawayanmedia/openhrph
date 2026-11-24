<?php

namespace App\Services\Employees;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared query and CSV builder for the employee master file.
 */
final class EmployeeMasterFileQuery
{
    public const SORTABLE = [
        'employee_number',
        'last_name',
        'first_name',
        'date_hired',
        'basic_salary',
        'employment_status',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        $query = Employee::query()
            ->with(['office', 'department', 'employmentType', 'payrollGroup', 'bank', 'user']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        foreach ([
            'office_id',
            'department_id',
            'employment_type_id',
            'payroll_group_id',
        ] as $column) {
            $value = $filters[$column] ?? null;

            if ($value !== null && $value !== '') {
                $query->where($column, (int) $value);
            }
        }

        $status = $filters['employment_status'] ?? null;

        if ($status !== null && $status !== '') {
            $query->where('employment_status', (int) $status);
        }

        match ((string) ($filters['active'] ?? '')) {
            'active' => $query->whereNull('date_resigned'),
            'resigned' => $query->whereNotNull('date_resigned'),
            default => null,
        };

        $sort = (string) ($filters['sort'] ?? 'last_name');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'last_name';
        }

        return $query->orderBy($sort, $direction)->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Employee>
     */
    public function rows(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function csv(array $filters = []): string
    {
        $rows = [[
            'Employee no', 'Last name', 'First name', 'Middle name', 'Position',
            'Office', 'Department', 'Employment type', 'Employment status',
            'Date hired', 'Date resigned', 'Monthly basic', 'Daily rate',
            'Pay mode', 'Bank', 'Account name', 'Account number',
            'TIN', 'SSS / GSIS', 'PhilHealth', 'Pag-IBIG / HDMF', 'Portal email',
        ]];

        foreach ($this->rows($filters) as $employee) {
            $rows[] = [
                (string) $employee->employee_number,
                (string) $employee->last_name,
                (string) $employee->first_name,
                (string) $employee->middle_name,
                (string) $employee->position,
                (string) ($employee->office?->name ?? ''),
                (string) ($employee->department?->name ?? ''),
                (string) ($employee->employmentType?->name ?? ''),
                (string) $employee->employment_status_format,
                $employee->date_hired?->format('Y-m-d') ?? '',
                $employee->date_resigned?->format('Y-m-d') ?? '',
                $this->money($employee->getRawOriginal('basic_salary')),
                $this->money($employee->getRawOriginal('daily_rate')),
                (string) ($employee->pay_mode ?? 'bank'),
                (string) ($employee->bank?->name ?? ''),
                (string) $employee->bank_account_name,
                (string) $employee->bank_account_number,
                (string) $employee->tin_number,
                (string) ($employee->gsis_number ?: $employee->sss_number),
                (string) $employee->philhealth_number,
                (string) $employee->hdmf_number,
                (string) ($employee->user?->email ?? ''),
            ];
        }

        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    private function money(mixed $centavos): string
    {
        return number_format(((int) $centavos) / 100, 2, '.', '');
    }
}
