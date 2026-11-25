<div class="space-y-6">
    @php
        $identifier = $identifier ?? (string) ($employee->{$scheme->identifierField()} ?? '');
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.payroll.run', ['payroll' => $payroll->getKey()]) }}"
           class="text-sm text-slate-500 hover:text-navy-900 dark:text-slate-400 dark:hover:text-white">&larr; Back to payroll run</a>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="ui-btn-secondary">Print</button>
            @if (\App\Support\Installation\Installation::statutoryDriver() === 'ph')
                <a href="{{ route('payslip.show', $detail->getKey()) }}" target="_blank" class="ui-btn-primary">Download PDF</a>
            @else
                <span class="text-xs text-slate-500 dark:text-slate-400">PDF payslip uses the Philippine form; use this page for other countries.</span>
            @endif
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
                <p class="text-slate-500 dark:text-slate-400">Monthly rate {{ currency_symbol() }}{{ number_format($detail->basic_monthly_salary, 2) }} · Daily rate {{ currency_symbol() }}{{ number_format($detail->daily_rate, 2) }}</p>
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
                    ] as $label => $amount)
                        @continue((float) $amount === 0.0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">{{ $label }}</dt>
                            <dd class="font-medium tabular-nums text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($amount, 2) }}</dd>
                        </div>
                    @endforeach
                    @foreach ($detail->compensation_lines as $line)
                        @continue($line['amount'] === 0.0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">{{ $line['name'] }}</dt>
                            <dd class="font-medium tabular-nums text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($line['amount'], 2) }}</dd>
                        </div>
                    @endforeach
                    @foreach ([
                        'Other incentives' => $detail->unitemised_incentive,
                        'Bonus' => $detail->bonus,
                        'Commission' => $detail->commission,
                        '13th month' => $detail->thirteenth_month,
                        'Leave conversion' => $detail->leave_conversion,
                        'Separation pay' => $detail->separation_pay,
                    ] as $label => $amount)
                        @continue((float) $amount === 0.0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">{{ $label }}</dt>
                            <dd class="font-medium tabular-nums text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($amount, 2) }}</dd>
                        </div>
                    @endforeach
                    @if ((float) ($detail->undertime_pay ?? 0) > 0)
                        <div class="flex items-center justify-between py-1">
                            <dt class="text-slate-600 dark:text-slate-300">Undertime ({{ rtrim(rtrim(number_format((float) $detail->undertime_hours, 2), '0'), '.') }} h)</dt>
                            <dd class="font-medium tabular-nums text-rose-600">-{{ currency_symbol() }}{{ number_format($detail->undertime_pay / 100, 2) }}</dd>
                        </div>
                    @endif

                    @if ((float) $detail->absent_days_amount > 0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">Absences ({{ rtrim(rtrim(number_format($detail->absent_days, 2), '0'), '.') }} days)</dt>
                            <dd class="font-medium tabular-nums text-rose-600">-{{ currency_symbol() }}{{ number_format($detail->absent_days_amount, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-2 dark:border-navy-800">
                        <dt class="font-semibold text-navy-900 dark:text-white">Gross pay</dt>
                        <dd class="font-semibold tabular-nums text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($detail->gross_income, 2) }}</dd>
                    </div>
                </dl>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Deductions</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @php
                        $deductionLines = $detail->relationLoaded('deductionLines') ? $detail->deductionLines : collect();
                        $deductions = $deductionLines->isNotEmpty()
                            ? $deductionLines->map(fn ($line) => [$line->name ?: $line->code, $line->employee_centavos / 100])->values()->all()
                            : [
                                $scheme->code() === 'gsis'
                                    ? ['GSIS', $detail->gsis_employee]
                                    : ['SSS', $detail->sss_employee + $detail->sss_provident_employee],
                                ['PhilHealth', $detail->philhealth_employee],
                                ['Pag-IBIG', $detail->hdmf_employee],
                                ['Withholding tax', $detail->withholding_tax],
                                ['Loans', $detail->loans],
                                ['Other deductions', max(0, $detail->total_deductions - $detail->total_employee - $detail->withholding_tax)],
                            ];
                    @endphp
                    @foreach ($deductions as [$label, $amount])
                        @continue((float) $amount === 0.0)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-300">{{ $label }}</dt>
                            <dd class="font-medium tabular-nums text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($amount, 2) }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-2 dark:border-navy-800">
                        <dt class="font-semibold text-navy-900 dark:text-white">Total deductions</dt>
                        <dd class="font-semibold tabular-nums text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($detail->total_deductions, 2) }}</dd>
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
                <p class="text-3xl font-semibold tabular-nums">{{ currency_symbol() }}{{ number_format($detail->net_pay, 2) }}</p>
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-3 text-xs text-slate-500 sm:grid-cols-2 dark:text-slate-400">
            <p>TIN: {{ mask_id($employee->tin_number) }}</p>
            <p>{{ $scheme->identifierLabel() }}: {{ mask_id($identifier) }}</p>
            <p>PhilHealth: {{ mask_id($employee->philhealth_number) }}</p>
            <p>Pag-IBIG: {{ mask_id($employee->hdmf_number) }}</p>
            <p>Employer: {{ config('app.name') }}</p>
            <p>Prepared by: {{ $payroll->processedBy?->name ?? '—' }} · Approved by: {{ $payroll->approvedBy?->name ?? '—' }}</p>
        </section>

        <section class="mt-4 grid grid-cols-1 gap-4 text-xs text-slate-500 sm:grid-cols-3 dark:text-slate-400">
            <p>Employer share: {{ currency_symbol() }}{{ number_format($detail->total_employer, 2) }}</p>
            <p>Taxable income: {{ currency_symbol() }}{{ number_format($detail->taxable_income, 2) }}</p>
            <p>Payroll #{{ $payroll->getKey() }}</p>
        </section>

        @if (config('app.debug'))
            @php
                $taxRows = $taxReport['rows'] ?? [];
                $taxSummary = $taxReport['summary'] ?? [];
                $basisLabels = [
                    'monthly' => 'per month',
                    'annual' => 'per year',
                    'semester' => 'per semester',
                    'hourly_ot' => 'per OT hour',
                    'catalogue' => 'catalogue maximum',
                ];
                $money = fn (?float $amount): string => $amount === null
                    ? '—'
                    : currency_symbol().number_format($amount, 2);
            @endphp

            <section class="mt-6 rounded-xl border border-dashed border-amber-400 bg-amber-50/70 p-4 text-xs print:hidden dark:border-amber-500/40 dark:bg-amber-500/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300">Debug · earnings tax treatment</h2>
                    <span class="rounded-full bg-amber-200/70 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-900 dark:bg-amber-500/20 dark:text-amber-200">APP_DEBUG</span>
                </div>

                @if (($taxReport['note'] ?? '') !== '')
                    <p class="mt-2 text-amber-800 dark:text-amber-200">{{ $taxReport['note'] }}</p>
                @endif

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-[10px] uppercase tracking-wider text-amber-800/80 dark:text-amber-300/80">
                            <tr>
                                <th class="py-1 pr-3 font-semibold">Earning</th>
                                <th class="py-1 pr-3 text-right font-semibold">Amount</th>
                                <th class="py-1 pr-3 font-semibold">Treatment</th>
                                <th class="py-1 pr-3 text-right font-semibold">Maximum</th>
                                <th class="py-1 pr-3 text-right font-semibold">Non-taxable</th>
                                <th class="py-1 text-right font-semibold">Exceeded</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-200/70 dark:divide-amber-500/20">
                            @forelse ($taxRows as $row)
                                <tr data-tax-row="{{ \Illuminate\Support\Str::slug($row['label']) }}">
                                    <td class="py-1 pr-3 text-slate-700 dark:text-slate-200">
                                        {{ $row['label'] }}
                                        @if (($row['source'] ?? '') !== '')
                                            <span class="block text-[10px] text-amber-700/80 dark:text-amber-300/70">{{ $row['source'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-1 pr-3 text-right tabular-nums">{{ $money($row['amount']) }}</td>
                                    <td class="py-1 pr-3">
                                        @if ($row['taxable'] === null)
                                            <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">Unknown</span>
                                        @elseif (! $row['taxable'])
                                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800">Non-taxable</span>
                                        @elseif (($row['exempt'] ?? 0.0) > 0 && ($row['excess'] ?? 0.0) > 0)
                                            <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800">Partly taxable</span>
                                        @elseif (($row['exempt'] ?? 0.0) > 0)
                                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800">Non-taxable</span>
                                        @else
                                            <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-800">Taxable</span>
                                        @endif
                                    </td>
                                    <td class="py-1 pr-3 text-right tabular-nums">
                                        @if ($row['ceiling'] === null)
                                            —
                                        @else
                                            {{ $money($row['ceiling']) }}
                                            <span class="block text-[10px] text-amber-700/80 dark:text-amber-300/70">{{ $basisLabels[$row['basis']] ?? $row['basis'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-1 pr-3 text-right tabular-nums">{{ ($row['exempt'] ?? 0) > 0 ? $money($row['exempt']) : '—' }}</td>
                                    <td data-tax-excess="{{ $row['excess'] ?? 0 }}"
                                        class="py-1 text-right tabular-nums {{ ($row['excess'] ?? 0) > 0 ? 'font-semibold text-rose-600 dark:text-rose-400' : '' }}">
                                        {{ ($row['excess'] ?? 0) > 0 ? $money($row['excess']) : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-2 text-slate-600 dark:text-slate-300">No earnings recorded for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 border-t border-amber-200/70 pt-2 text-[11px] text-amber-900 sm:grid-cols-2 dark:border-amber-500/20 dark:text-amber-200">
                    <div class="flex justify-between gap-3"><dt>Gross pay (persisted)</dt><dd class="tabular-nums">{{ $money($taxSummary['gross'] ?? null) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Non-taxable income (persisted)</dt><dd class="tabular-nums">{{ $money($taxSummary['non_taxable'] ?? null) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Benefit exemption (persisted)</dt><dd class="tabular-nums">{{ $money($taxSummary['benefit_exempt'] ?? null) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Minimum-wage exemption (persisted)</dt><dd class="tabular-nums">{{ $money($taxSummary['minimum_wage_exemption'] ?? null) }}</dd></div>
                    @if (($taxSummary['minimum_wage_exemption'] ?? 0) > 0)
                        <div class="flex justify-between gap-3">
                            <dt>Taxable before contributions (above minimum-wage exemption)</dt>
                            <dd class="tabular-nums">{{ $money(max(0.0, ($taxSummary['gross'] ?? 0.0) - ($taxSummary['minimum_wage_exemption'] ?? 0.0))) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3"><dt>Taxable income (persisted)</dt><dd class="tabular-nums">{{ $money($taxSummary['taxable'] ?? null) }}</dd></div>
                </dl>
            </section>
        @endif
    </article>
</div>
