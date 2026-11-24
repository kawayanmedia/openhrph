<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Government remittances</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Generate statutory working papers and track what has been filed and paid.
            </p>
        </div>

        @if ($payroll)
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="generateSchedule" wire:loading.attr="disabled" class="ui-btn-secondary">
                    Generate schedule
                </button>
                <a href="{{ route('admin.reports.remittances.download-all', ['payroll' => $payroll->getKey()]) }}" class="ui-btn-secondary">
                    Download all
                </a>
                <a href="{{ route('admin.reports.remittances.download', ['payroll' => $payroll->getKey(), 'format' => $format, 'agency' => $agency ?: null]) }}" class="ui-btn-primary">
                    Download {{ strtoupper($format) }}
                </a>
            </div>
        @endif
    </div>

    @if ($payrolls->isEmpty())
        <div class="ui-card p-8 text-center">
            <p class="text-sm font-semibold text-navy-900 dark:text-white">No processed payroll yet</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Process and approve a payroll before generating remittances.</p>
        </div>
    @else
        <div class="ui-card mb-6 p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="payroll" class="ui-label">Payroll</label>
                    <select id="payroll" wire:model.live="payrollId" class="ui-select">
                        @foreach ($payrolls as $option)
                            <option value="{{ $option->getKey() }}">
                                #{{ $option->getKey() }} · {{ $option->payroll_date?->format('M d, Y') ?? 'No date' }}
                                · {{ ucfirst(str_replace('_', ' ', (string) $option->type)) }}
                                ({{ ucfirst((string) $option->status) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="format" class="ui-label">Working paper</label>
                    <select id="format" wire:model.live="format" class="ui-select">
                        @foreach ($formats as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="agency" class="ui-label">Loan agency <span class="text-slate-400">(loans only)</span></label>
                    <input id="agency" type="text" wire:model.live.debounce.300ms="agency" class="ui-input" placeholder="e.g. GSIS" @disabled(! $isLoans)>
                </div>
            </div>
        </div>

        @if ($summary === [])
            <div class="ui-card mb-6 p-5 text-sm text-slate-500 dark:text-slate-400">
                This payroll has no statutory contributions to reconcile.
            </div>
        @else
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($summary as $row)
                    <div class="ui-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $row['agency'] }}</p>
                        <p class="mt-2 text-xl font-semibold text-navy-900 dark:text-white">{{ currency_symbol() }}{{ number_format($row['total'] / 100, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Employee {{ number_format($row['employee'] / 100, 2) }} · Employer {{ number_format($row['employer'] / 100, 2) }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="ui-card mb-6 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4 dark:border-navy-800">
                <div>
                    <h2 class="text-base font-semibold text-navy-900 dark:text-white">Remittance register</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Track filing references and payment dates for this payroll.</p>
                </div>
                <span class="text-xs text-slate-400">{{ $register->count() }} record(s)</span>
            </div>

            @if ($register->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    No schedule yet. Choose <span class="font-semibold">Generate schedule</span> to create the agency rows for this payroll.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="ui-th">Agency</th>
                                <th class="ui-th">Period</th>
                                <th class="ui-th">Due</th>
                                <th class="ui-th text-right">Employee</th>
                                <th class="ui-th text-right">Employer</th>
                                <th class="ui-th text-right">Total</th>
                                <th class="ui-th">Status</th>
                                <th class="ui-th">Reference</th>
                                <th class="ui-th text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($register as $remittance)
                                <tr>
                                    <td class="ui-td font-semibold text-navy-900 dark:text-white">{{ ucfirst($remittance->agency) }}</td>
                                    <td class="ui-td">{{ $remittance->period_from?->format('M d') }} – {{ $remittance->period_to?->format('M d, Y') }}</td>
                                    <td class="ui-td">{{ $remittance->due_date?->format('M d, Y') ?? '—' }}</td>
                                    <td class="ui-td text-right">{{ number_format($remittance->employee_amount / 100, 2) }}</td>
                                    <td class="ui-td text-right">{{ number_format($remittance->employer_amount / 100, 2) }}</td>
                                    <td class="ui-td text-right font-semibold">{{ number_format($remittance->total_amount / 100, 2) }}</td>
                                    <td class="ui-td">
                                        @php($tone = match ($remittance->status) {
                                            'paid' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                            'filed' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                                            'void' => 'bg-slate-100 text-slate-500 dark:bg-navy-800 dark:text-slate-400',
                                            default => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                        })
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone }}">{{ $remittance->status_label }}</span>
                                    </td>
                                    <td class="ui-td">
                                        <span class="block">{{ $remittance->reference_number ?: '—' }}</span>
                                        @if ($remittance->paid_at)
                                            <span class="text-xs text-slate-400">Paid {{ $remittance->paid_at->format('M d, Y') }}</span>
                                        @elseif ($remittance->filed_at)
                                            <span class="text-xs text-slate-400">Filed {{ $remittance->filed_at->format('M d, Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="ui-td">
                                        <div class="flex flex-wrap justify-end gap-1.5">
                                            @if ($remittance->status === 'pending')
                                                <button type="button" wire:click="openReferenceModal({{ $remittance->getKey() }}, 'filed')" class="ui-btn-secondary !px-2 !py-1 text-xs">Mark filed</button>
                                            @endif
                                            @if (in_array($remittance->status, ['pending', 'filed'], true))
                                                <button type="button" wire:click="openReferenceModal({{ $remittance->getKey() }}, 'paid')" class="ui-btn-primary !px-2 !py-1 text-xs">Mark paid</button>
                                                <button type="button" wire:click="void({{ $remittance->getKey() }})" wire:confirm="Void this remittance row?" class="ui-btn-ghost !px-2 !py-1 text-xs text-rose-600">Void</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="ui-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4 dark:border-navy-800">
                <div>
                    <h2 class="text-base font-semibold text-navy-900 dark:text-white">{{ $formats[$format] ?? strtoupper($format) }} preview</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">The downloaded CSV includes the control-total row.</p>
                </div>
                <span class="text-xs text-slate-400">Showing {{ max(0, count($preview) - 1) }} row(s)</span>
            </div>

            @if (count($preview) <= 1)
                <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No rows for this working paper.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                @foreach ($preview[0] as $header)
                                    <th class="ui-th">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($preview, 1, 50) as $row)
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

    @if ($showReferenceModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4 backdrop-blur-sm" wire:keydown.escape="closeReferenceModal">
            <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-navy-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-navy-800">
                    <h3 class="text-lg font-semibold text-navy-900 dark:text-white">
                        {{ $modalMode === 'paid' ? 'Mark remittance paid' : 'Mark remittance filed' }}
                    </h3>
                </div>

                <form wire:submit="saveReference" class="space-y-4 px-5 py-4">
                    <div>
                        <label for="modalReference" class="ui-label">Reference number</label>
                        <input id="modalReference" type="text" wire:model="modalReference" class="ui-input" required>
                        @error('modalReference') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="modalDate" class="ui-label">{{ $modalMode === 'paid' ? 'Payment date' : 'Filing date' }}</label>
                        <input id="modalDate" type="date" wire:model="modalDate" class="ui-input" required>
                        @error('modalDate') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="modalNotes" class="ui-label">Notes</label>
                        <textarea id="modalNotes" wire:model="modalNotes" rows="3" class="ui-textarea"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeReferenceModal" class="ui-btn-secondary">Cancel</button>
                        <button type="submit" class="ui-btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
