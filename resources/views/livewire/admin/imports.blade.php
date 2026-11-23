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
            <form wire:submit="previewWorkbook" class="ui-form-card" novalidate>
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
                            wire:target="file,previewWorkbook"
                            class="ui-btn-primary">
                        <span wire:loading.remove wire:target="previewWorkbook">{{ $previewed ? 'Preview again' : 'Preview workbook' }}</span>
                        <span wire:loading wire:target="previewWorkbook">Previewing…</span>
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

            @if ($previewed && $preview !== [])
                <div class="ui-card overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-navy-800">
                        <div>
                            <h2 class="text-base font-semibold text-navy-900 dark:text-white">Preview</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                @if ($preview['totals']['total'] > 0)
                                    {{ $preview['totals']['created'] }} to create · {{ $preview['totals']['updated'] }} to update
                                @else
                                    No valid rows to import.
                                @endif
                            </p>
                        </div>

                        @if ($preview['clean'] && $preview['totals']['total'] > 0)
                            <button type="button"
                                    wire:click="import"
                                    wire:loading.attr="disabled"
                                    wire:target="import"
                                    class="ui-btn-primary shrink-0">
                                <span wire:loading.remove wire:target="import">
                                    Import {{ $preview['totals']['total'] }} {{ \Illuminate\Support\Str::plural('record', $preview['totals']['total']) }}
                                </span>
                                <span wire:loading wire:target="import">Importing…</span>
                            </button>
                        @endif
                    </div>

                    @if (! $preview['clean'])
                        <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            Fix the problems above, then preview the workbook again. Importing stays disabled until the preview is clean.
                        </div>
                    @endif

                    <div class="divide-y divide-slate-100 dark:divide-navy-800">
                        @forelse ($preview['sheets'] as $sheet)
                            <div class="px-5 py-4" wire:key="preview-{{ $sheet['key'] }}">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-navy-900 dark:text-white">{{ $sheet['title'] }}</h3>
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        {{ $sheet['created'] }} new
                                    </span>
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                                        {{ $sheet['updated'] }} update
                                    </span>
                                </div>

                                @if ($sheet['rows'] === [])
                                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                        No valid rows to preview for this sheet.
                                    </p>
                                @else
                                    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-navy-800">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-slate-50 dark:bg-navy-950">
                                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                    <th class="px-3 py-2 font-semibold">Row</th>
                                                    <th class="px-3 py-2 font-semibold">Action</th>
                                                    @foreach ($sheet['columns'] as $column)
                                                        <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ $column }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-navy-800">
                                                @foreach ($sheet['rows'] as $row)
                                                    <tr class="align-top">
                                                        <td class="whitespace-nowrap px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['row'] }}</td>
                                                        <td class="whitespace-nowrap px-3 py-2">
                                                            @if ($row['action'] === 'create')
                                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">New</span>
                                                            @else
                                                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Update</span>
                                                            @endif
                                                        </td>
                                                        @foreach ($sheet['columns'] as $column)
                                                            <td class="whitespace-nowrap px-3 py-2 text-slate-700 dark:text-slate-200">
                                                                {{ $row['values'][$column] ?? '' }}
                                                                @isset($row['references'][$column])
                                                                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-medium text-slate-600 dark:bg-navy-800 dark:text-slate-300">
                                                                        {{ $row['references'][$column] }}
                                                                    </span>
                                                                @endisset
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                    @if ($row['warnings'] !== [])
                                                        <tr>
                                                            <td colspan="{{ count($sheet['columns']) + 2 }}" class="bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                                                <ul class="space-y-1">
                                                                    @foreach ($row['warnings'] as $warning)
                                                                        <li>{{ $warning }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">
                                No sheets in this workbook contain data.
                            </div>
                        @endforelse
                    </div>

                    @if ($preview['empty_sheets'] !== [])
                        <div class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500 dark:border-navy-800 dark:text-slate-400">
                            Skipped {{ \Illuminate\Support\Str::plural('empty sheet', count($preview['empty_sheets'])) }}:
                            {{ implode(', ', $preview['empty_sheets']) }}
                        </div>
                    @endif
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
                    <li>Preview first: nothing is written until you confirm a clean preview.</li>
                    <li>Everything is validated before importing. One bad cell stops the whole import.</li>
                    <li>Rows are matched on their key columns, so existing records are updated instead of duplicated.</li>
                    <li>Blank cells leave an existing value unchanged.</li>
                    <li>A reference such as Office must be in the workbook or already exist for your company.</li>
                    <li>Formulas are not imported; paste the calculated values.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>
