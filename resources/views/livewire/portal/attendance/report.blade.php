<div class="px-4 py-5 sm:p-6">

    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $title }}</h3>
    </div>

    <div class="bg-white px-4 py-5 shadow sm:rounded-lg sm:p-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="mt-5 md:col-span-2 md:mt-0">
                <div class="grid grid-cols-6 gap-6">

                    <div class="col-span-6 sm:col-span-3">
                        <label for="from" class="block text-sm font-medium text-gray-700">From</label>
                        <input wire:model.live="dateFrom" type="date" id="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        @error('dateFrom') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label for="to" class="block text-sm font-medium text-gray-700">To</label>
                        <input wire:model.live="dateTo" type="date" id="to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        @error('dateTo') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <button wire:click.prevent="changeDate" class="inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Generate</button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
            @if (count($rows) != 0)
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Date</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Day</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Shift Type</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Shift</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Logs</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Late</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Undertime</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total Hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ $row->log_date->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->log_date->format('D') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->schedule?->name }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{!! $row->day_type_format !!}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->bio_logs }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->late }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->ut }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->total_hours }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
            <div class="text-gray-500">Nothing here...</div>
            @endif
            </div>
        </div>
    </div>
</div>