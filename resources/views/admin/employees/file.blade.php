@php
    $employee = $employee;
    $money = fn ($centavos) => number_format(((int) $centavos) / 100, 2, '.', '');
    $value = fn ($value) => filled($value) ? $value : '—';
    $address = collect([
        $company?->address_no,
        $company?->street,
        $company?->zip_code,
    ])->filter()->implode(', ');
@endphp

<x-layouts.print :title="'201 File · '.$employee->fullname">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 no-print">
        <a href="{{ url()->previous() }}" class="ui-btn-secondary">&larr; Back</a>
        <div class="flex gap-2">
            <a href="{{ route('admin.reports.employee-master-file') }}" class="ui-btn-secondary">Master file</a>
            <button type="button" onclick="window.print()" class="ui-btn-primary">Print / Save PDF</button>
        </div>
    </div>

    <article class="print-sheet overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-8 py-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-navy-900">{{ $company?->name ?? config('app.name') }}</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ $value($company?->trade_name) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $value($address) }}</p>
                    <p class="mt-1 text-xs text-slate-500">TIN {{ $value($company?->tin) }} · SSS {{ $value($company?->sss) }} · PhilHealth {{ $value($company?->phil_health) }} · HDMF {{ $value($company?->hdmf) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-teal-700">Employee 201 File</p>
                    <p class="mt-2 text-sm font-semibold text-navy-900">{{ $employee->last_name }}, {{ $employee->first_name }} {{ $employee->middle_name }}</p>
                    <p class="text-xs text-slate-500">Employee no. {{ $value($employee->employee_number) }}</p>
                    <p class="text-xs text-slate-500">Printed {{ now()->format('M d, Y g:i A') }}</p>
                </div>
            </div>
        </header>

        <div class="space-y-8 px-8 py-6">
            <section>
                <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Personal information</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs font-medium text-slate-500">Full name</dt><dd class="mt-0.5 font-medium">{{ $value(trim($employee->first_name.' '.$employee->middle_name.' '.$employee->last_name.' '.$employee->extension)) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Sex</dt><dd class="mt-0.5">{{ $value($employee->sex) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Birth date</dt><dd class="mt-0.5">{{ $employee->birth_date ? \Carbon\Carbon::parse($employee->birth_date)->format('M d, Y') : '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-medium text-slate-500">Residential address</dt><dd class="mt-0.5">{{ $value($employee->res_address) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Emergency contact</dt><dd class="mt-0.5">{{ $value($employee->emergency_contact) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Emergency number</dt><dd class="mt-0.5">{{ $value($employee->emergency_contact_no) }}</dd></div>
                </dl>
            </section>

            <section>
                <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Government identifiers</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs font-medium text-slate-500">TIN</dt><dd class="mt-0.5 font-mono">{{ $value($employee->tin_number) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">SSS</dt><dd class="mt-0.5 font-mono">{{ $value($employee->sss_number) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">GSIS BP</dt><dd class="mt-0.5 font-mono">{{ $value($employee->gsis_number) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">PhilHealth</dt><dd class="mt-0.5 font-mono">{{ $value($employee->philhealth_number) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Pag-IBIG / HDMF</dt><dd class="mt-0.5 font-mono">{{ $value($employee->hdmf_number) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Tax status</dt><dd class="mt-0.5">{{ $value($employee->tax_status) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Dependents</dt><dd class="mt-0.5">{{ $value($employee->dependents) }}</dd></div>
                </dl>

                @if ($employee->identifiers->isNotEmpty())
                    <table class="mt-4 w-full border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500">
                                <th class="py-1.5 pr-2">Type</th>
                                <th class="py-1.5 pr-2">Number</th>
                                <th class="py-1.5 pr-2">Issued</th>
                                <th class="py-1.5">Expires</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->identifiers as $identifier)
                                <tr class="border-b border-slate-100">
                                    <td class="py-1.5 pr-2">{{ $identifier->type }}</td>
                                    <td class="py-1.5 pr-2 font-mono">{{ $identifier->number }}</td>
                                    <td class="py-1.5 pr-2">{{ $identifier->issued_at?->format('M d, Y') ?? '—' }}</td>
                                    <td class="py-1.5">{{ $identifier->expires_at?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section>
                <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Employment</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs font-medium text-slate-500">Position</dt><dd class="mt-0.5">{{ $value($employee->position) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Office</dt><dd class="mt-0.5">{{ $value($employee->office?->name) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Department</dt><dd class="mt-0.5">{{ $value($employee->department?->name) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Immediate head</dt><dd class="mt-0.5">{{ $value(trim(($employee->immediateHead?->first_name ?? '').' '.($employee->immediateHead?->last_name ?? ''))) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Employment type</dt><dd class="mt-0.5">{{ $value($employee->employmentType?->name) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Employment status</dt><dd class="mt-0.5">{{ $employee->employment_status_format }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Date hired</dt><dd class="mt-0.5">{{ $value($employee->date_hired?->format('M d, Y')) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Date resigned</dt><dd class="mt-0.5">{{ $value($employee->date_resigned?->format('M d, Y')) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Salary grade / step</dt><dd class="mt-0.5">{{ $value(trim(($employee->salary_grade ?? '').' / '.($employee->step ?? ''), ' /')) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Item number</dt><dd class="mt-0.5">{{ $value($employee->item_number) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Payroll group</dt><dd class="mt-0.5">{{ $value($employee->payrollGroup?->name) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Portal email</dt><dd class="mt-0.5">{{ $value($employee->user?->email) }}</dd></div>
                </dl>
            </section>

            <section>
                <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Compensation and bank</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs font-medium text-slate-500">Payment type</dt><dd class="mt-0.5">{{ ucfirst((string) $employee->payment_type) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Monthly basic</dt><dd class="mt-0.5">{{ $money($employee->getRawOriginal('basic_salary')) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Daily rate</dt><dd class="mt-0.5">{{ $money($employee->getRawOriginal('daily_rate')) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Pay mode</dt><dd class="mt-0.5">{{ ucfirst((string) ($employee->pay_mode ?? 'bank')) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Bank</dt><dd class="mt-0.5">{{ $value($employee->bank?->name) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Account name</dt><dd class="mt-0.5">{{ $value($employee->bank_account_name) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Account number</dt><dd class="mt-0.5 font-mono">{{ $value($employee->bank_account_number) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Branch</dt><dd class="mt-0.5">{{ $value($employee->bank_branch) }}</dd></div>
                </dl>
            </section>

            <section>
                <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Leave balances</h2>
                <dl class="grid grid-cols-3 gap-4 text-sm">
                    <div><dt class="text-xs font-medium text-slate-500">Vacation leave</dt><dd class="mt-0.5 text-lg font-semibold">{{ $value($employee->total_vacation_leave) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Sick leave</dt><dd class="mt-0.5 text-lg font-semibold">{{ $value($employee->total_sick_leave) }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Personal leave</dt><dd class="mt-0.5 text-lg font-semibold">{{ $value($employee->total_personal_leave) }}</dd></div>
                </dl>
            </section>

            @if ($employee->compensations->isNotEmpty())
                <section>
                    <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Compensations</h2>
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                                <th class="py-1.5 pr-2">Compensation</th>
                                <th class="py-1.5 pr-2">Group</th>
                                <th class="py-1.5 pr-2 text-right">Amount</th>
                                <th class="py-1.5">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->compensations as $row)
                                <tr class="border-b border-slate-100">
                                    <td class="py-1.5 pr-2">{{ $row->compensation?->name ?? '—' }}</td>
                                    <td class="py-1.5 pr-2">{{ $row->compensation?->group ?? '—' }}</td>
                                    <td class="py-1.5 pr-2 text-right">{{ $money($row->getRawOriginal('amount')) }}</td>
                                    <td class="py-1.5">{{ $row->status_format ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            @if ($employee->deductions->isNotEmpty())
                <section>
                    <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Deductions</h2>
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                                <th class="py-1.5 pr-2">Deduction</th>
                                <th class="py-1.5 pr-2 text-right">Amount</th>
                                <th class="py-1.5">Frequency</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->deductions as $row)
                                <tr class="border-b border-slate-100">
                                    <td class="py-1.5 pr-2">{{ $row->deduction?->name ?? '—' }}</td>
                                    <td class="py-1.5 pr-2 text-right">{{ $money($row->getRawOriginal('amount')) }}</td>
                                    <td class="py-1.5">{{ $value($row->frequency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            @if ($employee->loans->isNotEmpty())
                <section>
                    <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Loans</h2>
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                                <th class="py-1.5 pr-2">Loan</th>
                                <th class="py-1.5 pr-2 text-right">Amount</th>
                                <th class="py-1.5 pr-2 text-right">Balance</th>
                                <th class="py-1.5">Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->loans as $row)
                                <tr class="border-b border-slate-100">
                                    <td class="py-1.5 pr-2">{{ $row->loan?->name ?? $row->reference_number ?? '—' }}</td>
                                    <td class="py-1.5 pr-2 text-right">{{ $money($row->getRawOriginal('amount')) }}</td>
                                    <td class="py-1.5 pr-2 text-right">{{ $money($row->getRawOriginal('balance')) }}</td>
                                    <td class="py-1.5">{{ $row->is_active ? 'Yes' : 'No' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            @if ($employee->certificates->isNotEmpty() || $employee->trainings->isNotEmpty())
                <section>
                    <h2 class="mb-3 border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wider text-teal-700">Certificates and trainings</h2>
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                                <th class="py-1.5 pr-2">Record</th>
                                <th class="py-1.5 pr-2">Date</th>
                                <th class="py-1.5">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->certificates as $certificate)
                                <tr class="border-b border-slate-100">
                                    <td class="py-1.5 pr-2">{{ $certificate->certicate_title ?? 'Certificate' }} {{ $certificate->certicate_number ? '('.$certificate->certicate_number.')' : '' }}</td>
                                    <td class="py-1.5 pr-2">{{ $certificate->date?->format('M d, Y') ?? '—' }}</td>
                                    <td class="py-1.5">{{ $certificate->is_active ? 'Active' : 'Inactive' }}</td>
                                </tr>
                            @endforeach
                            @foreach ($employee->trainings as $training)
                                <tr class="border-b border-slate-100">
                                    <td class="py-1.5 pr-2">{{ $training->description ?? 'Training' }}</td>
                                    <td class="py-1.5 pr-2">{{ $training->date?->format('M d, Y') ?? '—' }}</td>
                                    <td class="py-1.5">{{ $training->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            <footer class="grid grid-cols-2 gap-10 pt-12">
                <div>
                    <div class="border-t border-slate-400 pt-1 text-xs text-slate-500">Employee signature</div>
                </div>
                <div>
                    <div class="border-t border-slate-400 pt-1 text-xs text-slate-500">Authorised HR officer</div>
                </div>
            </footer>
        </div>
    </article>
</x-layouts.print>
