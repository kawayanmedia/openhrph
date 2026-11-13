<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.payroll.run', ['payroll' => $payroll->getKey()]) }}"
           class="text-sm text-slate-500 hover:text-navy-900 dark:text-slate-400 dark:hover:text-white">&larr; Back to payroll run</a>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="ui-btn-secondary">Print</button>
            <a href="{{ route('payslip.show', $detail->getKey()) }}" target="_blank" class="ui-btn-primary">Download PDF</a>
        </div>
    </div>

    <article class="mx-auto max-w-3xl rounded-card border border-slate-200 bg-white p-8 shadow-panel print:border-0 print:shadow-none dark:border-navy-800 dark:bg-navy-900">
        <header class="flex items-start justify-between gap-6 border-b border-slate-200 pb-6 dark:border-navy-800">
            <div>
                <h1 class="text-xs font-semibold uppercase tracking-wider text-teal-700 dark:text-teal-400">Payslip</h1>
                <p class="mt-1 text-2xl font-semibold text-navy-900 dark:text-white">{{ config('app.name') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Pay date {{ $payroll->payroll_date?->format('F d, Y') }} ·
                    {{ $payroll->date_from?->format('M d, Y') }} – {{ $payroll->date_to?->format('M d, Y') }}
                </p>
            </div>

            <div class="text-right text-sm">
                <p class="font-semibold text-navy-900 dark:text-white">{{ trim($employee->first_name.' '.$employee->last_name) }}</p>
                <p class="text-slate-500 dark:text-slate-400">{{ $employee->employee_number }}</p>
                <p class="text-slate-500 dark:text-slate-400">{{ $employee->position }}</p>
                <p class="text-slate-500 dark:text-slate-400">Monthly rate ₱{{ number_format($detail->basic_monthly_salary, 2) }} · Daily rate ₱{{ number_format($detail->daily_rate, 2) }}</p>
            </div>
        </header>

        <section class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Earnings</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ([
                        'Basic pay (period)' => $detail->basic_salary,
                        'Attendance and premiums' => $detail->regular_pay - $detail->basic_salary,
                        'Overtime' => $detail->overtime_pay,
                        'Night differential' => $detail->night_pay,
                        'Rest day' => $detail->rest_day_pay,
                        'Holiday' => $detail->holiday_pay,
                        'Allowances' => $detail->allowance,
                        'Incentives' => $detail->incentive,
                        'Bonus' => $detail->bonus,
                        'Commission' => $detail->commission,
                        '13th month' => $detail->thirteenth_month,
                        'Leave conversion' => $detail->leave_conversion,
                        'Separation pay' => $detail->separation_pay,
                    ] as $label => $amount)
                        @continue((float) $amount === 0.0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">{{ $label }}</dt>
                            <dd class="font-medium tabular-nums text-navy-900 dark:text-white">₱{{ number_format($amount, 2) }}</dd>
                        </div>
                    @endforeach
                    @if ((float) $detail->absent_days_amount > 0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">Absences ({{ rtrim(rtrim(number_format($detail->absent_days, 2), '0'), '.') }} days)</dt>
                            <dd class="font-medium tabular-nums text-rose-600">-₱{{ number_format($detail->absent_days_amount, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-2 dark:border-navy-800">
                        <dt class="font-semibold text-navy-900 dark:text-white">Gross pay</dt>
                        <dd class="font-semibold tabular-nums text-navy-900 dark:text-white">₱{{ number_format($detail->gross_income, 2) }}</dd>
                    </div>
                </dl>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Deductions</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ([
                        'SSS' => $detail->sss_employee + $detail->sss_provident_employee,
                        'PhilHealth' => $detail->philhealth_employee,
                        'Pag-IBIG' => $detail->hdmf_employee,
                        'Withholding tax' => $detail->withholding_tax,
                        'Loans' => $detail->loans,
                        'Other deductions' => max(0, $detail->total_deductions - $detail->total_employee - $detail->withholding_tax),
                    ] as $label => $amount)
                        @continue((float) $amount === 0.0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">{{ $label }}</dt>
                            <dd class="font-medium tabular-nums text-navy-900 dark:text-white">₱{{ number_format($amount, 2) }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-2 dark:border-navy-800">
                        <dt class="font-semibold text-navy-900 dark:text-white">Total deductions</dt>
                        <dd class="font-semibold tabular-nums text-navy-900 dark:text-white">₱{{ number_format($detail->total_deductions, 2) }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="mt-8 rounded-xl bg-navy-950 px-6 py-5 text-white dark:bg-navy-800">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-teal-300">Net pay</p>
                    <p class="mt-1 text-xs text-slate-300">Take-home for this period</p>
                </div>
                <p class="text-3xl font-semibold tabular-nums">₱{{ number_format($detail->net_pay, 2) }}</p>
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-3 text-xs text-slate-500 sm:grid-cols-2 dark:text-slate-400">
            <p>TIN: {{ mask_id($employee->tin_number) }}</p>
            <p>SSS: {{ mask_id($employee->sss_number) }}</p>
            <p>PhilHealth: {{ mask_id($employee->philhealth_number) }}</p>
            <p>Pag-IBIG: {{ mask_id($employee->hdmf_number) }}</p>
            <p>Employer: {{ config('app.name') }}</p>
            <p>Prepared by: {{ $payroll->processedBy?->name ?? '—' }} · Approved by: {{ $payroll->approvedBy?->name ?? '—' }}</p>
        </section>

        <section class="mt-4 grid grid-cols-1 gap-4 text-xs text-slate-500 sm:grid-cols-3 dark:text-slate-400">
            <p>Employer share: ₱{{ number_format($detail->total_employer, 2) }}</p>
            <p>Taxable income: ₱{{ number_format($detail->taxable_income, 2) }}</p>
            <p>Payroll #{{ $payroll->getKey() }}</p>
        </section>
    </article>
</div>
