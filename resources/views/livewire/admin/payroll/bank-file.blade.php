<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.payroll.run', $payroll) }}" class="text-sm text-slate-500 hover:text-navy-900 dark:text-slate-400 dark:hover:text-white">&larr; Payroll #{{ $payroll->getKey() }}</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Bank file</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $payroll->payrollGroup?->name ?? 'Payroll group' }} ·
                {{ $payroll->date_from?->format('M d, Y') }} – {{ $payroll->date_to?->format('M d, Y') }}
            </p>
        </div>

        @if ($template && $result && $result['included'] > 0)
            <a href="{{ route('admin.payroll.bank-file.download', [
                    'payroll' => $payroll->getKey(),
                    'template_id' => $template->getKey(),
                    'debit_account_id' => $debitAccountId,
                    'payment_date' => $paymentDate,
                ]) }}" class="ui-btn-primary">
                Download bank file
            </a>
        @endif
    </div>

    @if ($templates->isEmpty())
        <div class="ui-card p-8 text-center">
            <p class="text-sm font-semibold text-navy-900 dark:text-white">No bank file template yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a template to describe your bank's column layout.</p>
            <a href="{{ route('admin.resource.create', ['resource' => 'bank-file-template']) }}" class="ui-btn-primary mt-4">Create template</a>
        </div>
    @else
        <div class="ui-card p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="template" class="ui-label">Template</label>
                    <x-admin.searchable-select id="template" wire:model.live="templateId" label="Template">
                        @foreach ($templates as $option)
                            <option value="{{ $option->getKey() }}">{{ $option->name }}{{ $option->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </x-admin.searchable-select>
                </div>

                <div>
                    <label for="debitAccount" class="ui-label">Company debit account</label>
                    <x-admin.searchable-select id="debitAccount" wire:model.live="debitAccountId" label="Company debit account">
                        <option value="">— none —</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->getKey() }}">{{ $account->name }} · {{ $account->account_number }}</option>
                        @endforeach
                    </x-admin.searchable-select>
                </div>

                <div>
                    <label for="paymentDate" class="ui-label">Payment date</label>
                    <input id="paymentDate" type="date" wire:model.live="paymentDate" class="ui-input">
                </div>
            </div>
        </div>

        @if ($result)
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="ui-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Employees</p>
                    <p class="mt-2 text-2xl font-semibold text-navy-900 dark:text-white">{{ $result['included'] }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $result['excluded'] }} excluded (no net pay or non-bank mode)</p>
                </div>
                <div class="ui-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total credit</p>
                    <p class="mt-2 text-2xl font-semibold text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($result['total'] / 100, 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Net pay for the included employees</p>
                </div>
                <div class="ui-card p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Needs attention</p>
                    <p class="mt-2 text-2xl font-semibold {{ count($result['missing']) > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-navy-900 dark:text-white' }}">{{ count($result['missing']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Employees marked bank-paid without an account number</p>
                </div>
            </div>

            @if (count($result['missing']) > 0)
                <div class="ui-card overflow-hidden">
                    <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Employees missing bank details</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th class="ui-th">Employee no.</th>
                                    <th class="ui-th">Name</th>
                                    <th class="ui-th">Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($result['missing'], 0, 20) as $row)
                                    <tr>
                                        <td class="ui-td">{{ $row['employee_number'] }}</td>
                                        <td class="ui-td">{{ $row['name'] }}</td>
                                        <td class="ui-td">{{ $row['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (count($result['duplicates']) > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    <p class="font-semibold">Duplicate account numbers</p>
                    <p class="mt-1">{{ implode(', ', $result['duplicates']) }}</p>
                </div>
            @endif

            <div class="ui-card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4 dark:border-navy-800">
                    <div>
                        <h2 class="text-base font-semibold text-navy-900 dark:text-white">{{ $template?->name ?? 'Template' }} preview</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">The download contains every payable employee.</p>
                    </div>
                    <span class="text-xs text-slate-400">Showing {{ min(25, count($result['rows'])) }} of {{ count($result['rows']) }}</span>
                </div>

                @if (count($result['rows']) === 0)
                    <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        No payable employees with a bank account for this payroll.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    @foreach ($result['headers'] as $header)
                                        <th class="ui-th">{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($result['rows'], 0, 25) as $row)
                                    <tr>
                                        @foreach ($row as $cell)
                                            <td class="ui-td">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
