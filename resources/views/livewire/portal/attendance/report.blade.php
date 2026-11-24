<div class="ui-card p-5 sm:p-6">

    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ $title }}</h3>
    </div>

    <div class="ui-card p-5 sm:p-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="mt-5 md:col-span-2 md:mt-0">
                <div class="grid grid-cols-6 gap-6">

                    <div class="col-span-6 sm:col-span-3">
                        <label for="from" class="ui-label">From</label>
                        <input wire:model.live="dateFrom" type="date" id="from" class="ui-input mt-1">
                        @error('dateFrom') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label for="to" class="ui-label">To</label>
                        <input wire:model.live="dateTo" type="date" id="to" class="ui-input mt-1">
                        @error('dateTo') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <button wire:click.prevent="changeDate" class="inline-flex justify-center rounded-md border border-transparent bg-teal-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Generate</button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
            @if (count($rows) != 0)
                <div class="overflow-hidden shadow ring-1 ring-slate-900/5 dark:ring-white/10 md:rounded-lg">
                    <table class="ui-table">
                        <thead class="bg-slate-50 dark:bg-navy-800/40">
                            <tr>
                                <th scope="col" class="ui-th">Date</th>
                                <th scope="col" class="ui-th">Day</th>
                                <th scope="col" class="ui-th">Shift Type</th>
                                <th scope="col" class="ui-th">Shift</th>
                                <th scope="col" class="ui-th">Logs</th>
                                <th scope="col" class="ui-th">Late</th>
                                <th scope="col" class="ui-th">Undertime</th>
                                <th scope="col" class="ui-th">Total Hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-navy-800">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="ui-td font-medium text-navy-900 dark:text-white">{{ $row->log_date->format('M d, Y') }}</td>
                                <td class="ui-td">{{ $row->log_date->format('D') }}</td>
                                <td class="ui-td">{{ $row->schedule?->name }}</td>
                                <td class="ui-td">{!! $row->day_type_format !!}</td>
                                <td class="ui-td">{{ $row->bio_logs }}</td>
                                <td class="ui-td">{{ $row->late }}</td>
                                <td class="ui-td">{{ $row->ut }}</td>
                                <td class="ui-td">{{ $row->total_hours }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
            <div class="text-slate-500 dark:text-slate-400">Nothing here...</div>
            @endif
            </div>
        </div>
    </div>
</div>