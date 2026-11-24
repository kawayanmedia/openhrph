<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Employee master file</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                One row per employee, including statutory IDs and payroll bank details.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ $csvUrl }}" class="ui-btn-secondary">Export CSV</a>
            <button type="button" onclick="window.print()" class="ui-btn-secondary print:hidden">Print</button>
        </div>
    </div>

    <div class="ui-card p-5 print:hidden">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label for="search" class="ui-label">Search</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" class="ui-input" placeholder="Employee number, name or position">
            </div>

            <div>
                <label for="office" class="ui-label">Office</label>
                <select id="office" wire:model.live="officeId" class="ui-select">
                    <option value="">All offices</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->getKey() }}">{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="department" class="ui-label">Department</label>
                <select id="department" wire:model.live="departmentId" class="ui-select">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->getKey() }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="employmentType" class="ui-label">Employment type</label>
                <select id="employmentType" wire:model.live="employmentTypeId" class="ui-select">
                    <option value="">All types</option>
                    @foreach ($employmentTypes as $type)
                        <option value="{{ $type->getKey() }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="employmentStatus" class="ui-label">Employment status</label>
                <select id="employmentStatus" wire:model.live="employmentStatus" class="ui-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="payrollGroup" class="ui-label">Payroll group</label>
                <select id="payrollGroup" wire:model.live="payrollGroupId" class="ui-select">
                    <option value="">All groups</option>
                    @foreach ($payrollGroups as $group)
                        <option value="{{ $group->getKey() }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="active" class="ui-label">Status</label>
                <select id="active" wire:model.live="active" class="ui-select">
                    <option value="">Active and resigned</option>
                    <option value="active">Active only</option>
                    <option value="resigned">Resigned only</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 dark:border-navy-800">
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $employees->total() }} employee(s) · page total {{ currency_symbol() }}{{ number_format($totalMonthlyBasic / 100, 2) }}</p>
            <div class="flex items-center gap-2">
                <label for="perPage" class="text-xs text-slate-500 dark:text-slate-400">Rows</label>
                <select id="perPage" wire:model.live="perPage" class="ui-select ui-select-auto py-1.5">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="resetFilters" class="ui-btn-ghost text-xs">Reset filters</button>
            </div>
        </div>
    </div>

    <div class="ui-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th class="ui-th">
                            <button type="button" wire:click="sortBy('employee_number')" class="hover:text-teal-700 dark:hover:text-teal-300">Employee no.</button>
                        </th>
                        <th class="ui-th">
                            <button type="button" wire:click="sortBy('last_name')" class="hover:text-teal-700 dark:hover:text-teal-300">Employee</button>
                        </th>
                        <th class="ui-th">Position</th>
                        <th class="ui-th">Office / Department</th>
                        <th class="ui-th">Type / Status</th>
                        <th class="ui-th">
                            <button type="button" wire:click="sortBy('date_hired')" class="hover:text-teal-700 dark:hover:text-teal-300">Hired</button>
                        </th>
                        <th class="ui-th text-right">
                            <button type="button" wire:click="sortBy('basic_salary')" class="hover:text-teal-700 dark:hover:text-teal-300">Monthly basic</button>
                        </th>
                        <th class="ui-th">Statutory IDs</th>
                        <th class="ui-th">Bank account</th>
                        <th class="ui-th text-right print:hidden">201</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="ui-td font-mono text-xs">{{ $employee->employee_number }}</td>
                            <td class="ui-td">
                                <span class="block font-semibold text-navy-900 dark:text-white">{{ $employee->last_name }}, {{ $employee->first_name }} {{ $employee->middle_name }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $employee->user?->email ?? '—' }}</span>
                            </td>
                            <td class="ui-td">{{ $employee->position ?: '—' }}</td>
                            <td class="ui-td">
                                <span class="block">{{ $employee->office?->name ?? '—' }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $employee->department?->name ?? '—' }}</span>
                            </td>
                            <td class="ui-td">
                                <span class="block">{{ $employee->employmentType?->name ?? '—' }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $employee->employment_status_format }}</span>
                            </td>
                            <td class="ui-td">{{ $employee->date_hired?->format('M d, Y') ?? '—' }}</td>
                            <td class="ui-td text-right">{{ number_format($employee->getRawOriginal('basic_salary') / 100, 2) }}</td>
                            <td class="ui-td">
                                <span class="block text-xs">TIN {{ $employee->tin_number ?: '—' }}</span>
                                <span class="block text-xs">{{ $employee->gsis_number ? 'GSIS '.$employee->gsis_number : 'SSS '.($employee->sss_number ?: '—') }}</span>
                                <span class="block text-xs">PHIC {{ $employee->philhealth_number ?: '—' }} · HDMF {{ $employee->hdmf_number ?: '—' }}</span>
                            </td>
                            <td class="ui-td">
                                @if ($employee->bank_account_number)
                                    <span class="block">{{ $employee->bank?->name ?? '—' }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">••••{{ substr($employee->bank_account_number, -4) }}</span>
                                @else
                                    <span class="text-xs text-amber-600 dark:text-amber-300">Missing</span>
                                @endif
                            </td>
                            <td class="ui-td text-right print:hidden">
                                <a href="{{ route('admin.employees.file', $employee) }}" class="ui-btn-secondary !px-2 !py-1 text-xs">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="ui-td py-10 text-center text-slate-500 dark:text-slate-400">No employees match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="print:hidden">{{ $employees->links() }}</div>
</div>
