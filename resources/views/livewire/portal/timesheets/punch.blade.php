<div class="px-4 py-5 sm:p-6">
    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $title }}</h3>
            <p class="mt-1 text-sm text-gray-500">Record your time in and time out for today.</p>
        </div>
    </div>

    @if ($today === null)
        <div class="mt-6 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
            Your account is not linked to an employee record yet. Contact HR to link it before recording attendance.
        </div>
    @else
        @if ($status)
            <div class="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800">
                {{ $status }}
            </div>
        @endif

        @error('punch')
            <div class="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-800">
                {{ $message }}
            </div>
        @enderror

        <div class="mt-6 overflow-hidden bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ $today['log_date'] }}</p>
                        <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ now()->format('h:i A') }}</p>
                        <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-600">
                            <div>
                                <dt class="inline font-medium text-gray-900">Schedule:</dt>
                                <dd class="inline">{{ $today['schedule'] ?? 'Not assigned' }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-gray-900">Time in:</dt>
                                <dd class="inline">{{ $today['time_in'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-gray-900">Time out:</dt>
                                <dd class="inline">{{ $today['time_out'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-gray-900">Hours:</dt>
                                <dd class="inline">{{ $today['total_hours'] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-6 flex gap-3 sm:mt-0">
                        <button
                            type="button"
                            wire:click="timeIn"
                            wire:loading.attr="disabled"
                            @disabled(! $today['can_record'] || $today['next'] !== 0)
                            class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            Time In
                        </button>
                        <button
                            type="button"
                            wire:click="timeOut"
                            wire:loading.attr="disabled"
                            @disabled(! $today['can_record'] || $today['next'] !== 1)
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400"
                        >
                            Time Out
                        </button>
                    </div>
                </div>

                @unless ($today['can_record'])
                    <p class="mt-4 text-sm text-red-600">
                        {{ $today['locked'] ? 'This timesheet is locked.' : 'Attendance for this payroll period is closed.' }}
                    </p>
                @endunless

                <div class="mt-6">
                    <h4 class="text-sm font-medium text-gray-900">Today's punches</h4>

                    @if (count($today['logs']) === 0)
                        <p class="mt-2 text-sm text-gray-500">No punches recorded yet.</p>
                    @else
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($today['logs'] as $log)
                                <li class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                                    <span class="font-medium">{{ $log['label'] }}</span>
                                    <span class="ml-2">{{ $log['time'] }}</span>
                                    <span class="ml-2 text-xs text-gray-500">{{ $log['source'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-col">
            <h4 class="text-sm font-medium text-gray-900">Current payroll period ({{ $today['period'] }})</h4>

            @if (count($rows) === 0)
                <p class="mt-2 text-sm text-gray-500">Nothing here yet.</p>
            @else
                <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Date</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Day Type</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Time In</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Time Out</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Punches</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Hours</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ $row['date'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ $row['day_type'] }}
                                                @if ($row['holiday'])
                                                    <span class="text-xs text-gray-400">({{ $row['holiday'] }})</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row['time_in'] ?? '—' }}</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row['time_out'] ?? '—' }}</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row['logs'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row['hours'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
