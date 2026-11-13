<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.resource.index', ['resource' => 'pay-roll']) }}"
               class="text-sm text-slate-500 hover:text-navy-900 dark:text-slate-400 dark:hover:text-white">&larr; Payrolls</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">
                Payroll #{{ $payroll->getKey() }}
            </h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $payroll->payrollGroup?->name ?? 'Payroll group' }} ·
                {{ $payroll->date_from?->format('M d, Y') }} – {{ $payroll->date_to?->format('M d, Y') }} ·
                pay date {{ $payroll->payroll_date?->format('M d, Y') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="preview" class="ui-btn-secondary" wire:loading.attr="disabled" wire:target="preview">
                <span wire:loading.remove wire:target="preview">Refresh preview</span>
                <span wire:loading wire:target="preview">Computing…</span>
            </button>

            @if (in_array($status, ['open', 'draft', 'failed', 'processed'], true))
                <button type="button" wire:click="process" class="ui-btn-primary" wire:loading.attr="disabled" wire:target="process"
                        wire:confirm="Process this payroll now? Details will be recalculated.">
                    Process payroll
                </button>
            @endif

            @if ($status === 'processed')
                <button type="button" wire:click="approve" class="ui-btn-primary"
                        wire:confirm="Approve and lock this payroll?">
                    Approve &amp; lock
                </button>
            @endif

            @if ($status === 'approved')
                <input type="text" wire:model="reopenReason" placeholder="Reason for reopening"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-navy-700 dark:bg-navy-900 dark:text-white">
                <button type="button" wire:click="reopen" class="ui-btn-secondary"
                        wire:confirm="Reopen this approved payroll? The reason is recorded in the audit trail.">
                    Reopen
                </button>
            @endif
        </div>
    </div>

    @if ($notice)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ $notice }}
        </div>
    @endif

    @if ($error)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            {{ $error }}
        </div>
    @endif

    @if ($missingEmployees > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">{{ $missingEmployees }} employee(s) have no attendance for this period</p>
            <p class="mt-1">If company policy pays the basic salary without a timesheet, enter a reason before processing.</p>
            <input type="text" wire:model="overrideReason" placeholder="Attendance override reason"
                   class="mt-2 w-full rounded-lg border border-amber-300 px-3 py-2 text-sm dark:border-amber-600 dark:bg-navy-900 dark:text-white">
        </div>
    @endif

    @if (in_array($status, ['processed', 'approved'], true))
        <div class="ui-card space-y-3 px-5 py-4 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-navy-900 dark:text-white">Review and segregation of duties</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Processed by {{ $payroll->processedBy?->name ?? '—' }} ·
                    Reviewed by {{ $payroll->reviewedBy?->name ?? 'not reviewed' }} ·
                    Approved by {{ $payroll->approvedBy?->name ?? '—' }}
                </span>
            </div>

            @if ($status === 'processed')
                <div class="flex flex-wrap items-end gap-2">
                    <input type="text" wire:model="reviewNote" placeholder="Review note (optional)"
                           class="min-w-64 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-navy-700 dark:bg-navy-900 dark:text-white">
                    <button type="button" wire:click="review" class="ui-btn-secondary">Mark reviewed</button>
                </div>

                <div>
                    <input type="text" wire:model="approvalOverrideReason" placeholder="Approval override reason (required without a review or when approving your own run)"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-navy-700 dark:bg-navy-900 dark:text-white">
                </div>
            @endif

            @if ($payroll->override_reason)
                <p class="text-xs text-amber-700 dark:text-amber-300">
                    Attendance override: {{ $payroll->override_reason }}
                    ({{ $payroll->overrideBy?->name ?? '—' }}, {{ $payroll->override_at?->format('M d, Y H:i') }})
                </p>
            @endif
        </div>
    @endif

    {{-- Stepper --}}
    @php
        $steps = [
            'period' => 'Period',
            'preview' => 'Preview',
            'process' => 'Process',
            'review' => 'Review',
            'approve' => 'Approve',
            'payslips' => 'Payslips',
        ];
        $rank = match ($status) {
            'open', 'draft', 'failed' => 1,
            'running' => 2,
            'processed' => 4,
            'approved', 'closed' => 5,
            default => 1,
        };
    @endphp

    <ol class="ui-card flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4 text-sm">
        @foreach ($steps as $key => $label)
            @php
                $index = $loop->iteration - 1;
            @endphp
            <li class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold
                    {{ $index < $rank ? 'bg-teal-500 text-white' : ($index === $rank ? 'bg-navy-900 text-white dark:bg-teal-400 dark:text-navy-950' : 'bg-slate-200 text-slate-500 dark:bg-navy-800 dark:text-slate-400') }}">
                    {{ $index < $rank ? '✓' : $loop->iteration }}
                </span>
                <span class="{{ $index <= $rank ? 'font-semibold text-navy-900 dark:text-white' : 'text-slate-500 dark:text-slate-400' }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $gross = collect($employees)->sum(fn ($row) => (float) ($row['gross'] ?? 0));
            $deductions = collect($employees)->sum(fn ($row) => (float) ($row['total_deductions'] ?? 0));
            $net = collect($employees)->sum(fn ($row) => (float) ($row['net_pay'] ?? 0));
        @endphp

        @foreach ([
            ['Employees', number_format(count($employees)), 'people in this run'],
            ['Gross pay', '₱'.number_format($gross, 2), 'earnings before deductions'],
            ['Deductions', '₱'.number_format($deductions, 2), 'statutory + other'],
            ['Net pay', '₱'.number_format($net, 2), 'take-home total'],
        ] as [$label, $value, $hint])
            <div class="ui-card px-5 py-4">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    @if ($warnings !== [])
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">{{ count($warnings) }} item(s) need attention</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach (array_slice(array_unique($warnings), 0, 8) as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Payslip register --}}
    <div class="ui-card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-navy-800">
            <h2 class="text-base font-semibold text-navy-900 dark:text-white">Payroll register</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">
                {{ $previewed ? 'Computed from attendance and statutory tables' : 'No preview yet' }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th class="ui-th">Employee</th>
                        <th class="ui-th">Gross</th>
                        <th class="ui-th">SSS</th>
                        <th class="ui-th">PhilHealth</th>
                        <th class="ui-th">Pag-IBIG</th>
                        <th class="ui-th">Tax</th>
                        <th class="ui-th">Deductions</th>
                        <th class="ui-th">Net pay</th>
                        <th class="ui-th text-right">Payslip</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-navy-800">
                    @forelse ($employees as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-navy-800/50">
                            <td class="ui-td">
                                <div class="font-medium text-navy-900 dark:text-white">{{ $row['name'] ?: '—' }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $row['employee_number'] ?? '' }}</div>
                            </td>
                            <td class="ui-td">₱{{ number_format((float) $row['gross'], 2) }}</td>
                            <td class="ui-td">₱{{ number_format((float) $row['sss_employee'], 2) }}</td>
                            <td class="ui-td">₱{{ number_format((float) $row['philhealth_employee'], 2) }}</td>
                            <td class="ui-td">₱{{ number_format((float) $row['pagibig_employee'], 2) }}</td>
                            <td class="ui-td">₱{{ number_format((float) $row['withholding_tax'], 2) }}</td>
                            <td class="ui-td">₱{{ number_format((float) $row['total_deductions'], 2) }}</td>
                            <td class="ui-td font-semibold text-navy-900 dark:text-white">₱{{ number_format((float) $row['net_pay'], 2) }}</td>
                            <td class="ui-td text-right">
                                @if ($status === 'processed' || $status === 'approved')
                                    <a href="{{ route('admin.payroll.payslip', ['payroll' => $payroll->getKey(), 'employee' => $row['employee_id']]) }}"
                                       class="text-sm font-semibold text-teal-700 hover:text-teal-800 dark:text-teal-400">View</a>
                                @else
                                    <span class="text-xs text-slate-400">Process first</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                No employees in this payroll group, or the preview has not run yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($status === 'running')
        <div wire:poll.3s class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
            Processing payroll… this page refreshes automatically.
        </div>
    @endif
</div>
