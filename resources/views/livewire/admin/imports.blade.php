<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Data import</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                Add or update offices, employees and payroll records from one Excel workbook.
                Every sheet is validated before anything is saved.
            </p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form wire:submit="import" class="ui-form-card" novalidate>
                <div class="ui-form-card-header">
                    <h2 class="text-base font-semibold text-navy-900 dark:text-white">Upload workbook</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Use the downloaded template so the sheet names and headings match.
                    </p>
                </div>

                <div class="ui-form-card-body">
                    <div class="sm:col-span-6">
                        <label for="import-file" class="ui-label">Excel workbook</label>

                        <input id="import-file"
                               type="file"
                               wire:model="file"
                               accept=".xlsx,.xls"
                               class="ui-file"
                               @error('file') aria-invalid="true" @enderror>

                        @error('file')
                            <p class="ui-error">{{ $message }}</p>
                        @enderror

                        <p class="ui-help">
                            .xlsx or .xls, up to {{ number_format(max(1, (int) config('imports.max_upload_kb', 10240) / 1024)) }} MB.
                            Leave sheets you do not need empty.
                        </p>

                        <p wire:loading wire:target="file" class="mt-2 text-xs font-medium text-teal-700 dark:text-teal-300">
                            Uploading…
                        </p>
                    </div>
                </div>

                <div class="ui-form-footer">
                    <div class="mr-auto flex flex-wrap gap-2">
                        <a href="{{ route('admin.import.template', ['sample' => 1]) }}" class="ui-btn-secondary">
                            Download sample
                        </a>
                        <a href="{{ route('admin.import.template', ['blank' => 1]) }}" class="ui-btn-secondary">
                            Download blank template
                        </a>
                    </div>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="file,import"
                            class="ui-btn-primary">
                        <span wire:loading.remove wire:target="import">Validate and import</span>
                        <span wire:loading wire:target="import">Validating…</span>
                    </button>
                </div>
            </form>

            @if ($problemCount > 0)
                <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-rose-200 px-5 py-4 dark:border-rose-500/30">
                        <div>
                            <h2 class="text-base font-semibold">
                                Import stopped — {{ $problemCount }} {{ \Illuminate\Support\Str::plural('problem', $problemCount) }} found
                            </h2>
                            <p class="mt-1 text-sm">
                                Nothing was saved. Fix the rows below in your workbook and upload it again.
                            </p>
                        </div>

                        <button type="button" wire:click="downloadProblems" class="ui-btn-secondary shrink-0">
                            Download problem report
                        </button>
                    </div>

                    <div class="space-y-5 px-5 py-4">
                        @foreach ($groupedProblems as $sheet => $problems)
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                                    {{ $sheet === 'Workbook' ? 'Workbook' : $sheet }}
                                </h3>

                                <div class="mt-2 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-rose-200 text-sm dark:divide-rose-500/30">
                                        <thead>
                                            <tr class="text-left text-xs uppercase tracking-wide text-rose-600 dark:text-rose-300">
                                                <th class="py-2 pr-4 font-semibold">Row</th>
                                                <th class="py-2 pr-4 font-semibold">Column</th>
                                                <th class="py-2 font-semibold">Problem</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-rose-100 dark:divide-rose-500/20">
                                            @foreach ($problems as $problem)
                                                <tr>
                                                    <td class="whitespace-nowrap py-2 pr-4 font-medium">
                                                        {{ $problem['row'] ?? '—' }}
                                                    </td>
                                                    <td class="whitespace-nowrap py-2 pr-4">
                                                        {{ $problem['column'] ? $problem['column'] . ($problem['heading'] ? ' · ' . $problem['heading'] : '') : ($problem['heading'] ?? '—') }}
                                                    </td>
                                                    <td class="py-2">{{ $problem['message'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach

                        @if ($displayedProblems < $problemCount)
                            <p class="text-xs font-medium">
                                Showing the first {{ $displayedProblems }} of {{ $problemCount }} problems.
                                Download the report to see all of them.
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            @if ($summary !== [])
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <div class="border-b border-emerald-200 px-5 py-4 dark:border-emerald-500/30">
                        <h2 class="text-base font-semibold">Import completed</h2>
                        <p class="mt-1 text-sm">The workbook was valid and has been saved.</p>
                    </div>

                    <ul class="divide-y divide-emerald-100 px-5 dark:divide-emerald-500/20">
                        @foreach ($summary as $sheet => $counts)
                            <li class="flex items-center justify-between gap-4 py-2.5 text-sm">
                                <span class="font-medium">{{ $sheet }}</span>
                                <span>
                                    {{ $counts['created'] }} created
                                    @if ($counts['updated'] > 0)
                                        · {{ $counts['updated'] }} updated
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="ui-card p-5">
                <h2 class="text-sm font-semibold text-navy-900 dark:text-white">What you can import</h2>

                <ul class="mt-3 space-y-2.5">
                    @foreach ($sheets as $sheet)
                        <li>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $sheet->title() }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Required: {{ implode(', ', $sheet->requiredHeadings()) ?: 'none' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="ui-card p-5">
                <h2 class="text-sm font-semibold text-navy-900 dark:text-white">How it works</h2>

                <ul class="mt-3 space-y-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    <li>Everything is validated first. One bad cell stops the whole import.</li>
                    <li>Rows are matched on their key columns, so existing records are updated instead of duplicated.</li>
                    <li>Blank cells leave an existing value unchanged.</li>
                    <li>A reference such as Office must be in the workbook or already exist for your company.</li>
                    <li>Formulas are not imported; paste the calculated values.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>
