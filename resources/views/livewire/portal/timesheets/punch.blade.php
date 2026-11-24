<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ $title }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Record your time in and time out for today.</p>
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
            <div class="mt-6 rounded-md bg-rose-50 dark:bg-rose-500/10 p-4 text-sm text-red-800">
                {{ $message }}
            </div>
        @enderror

        <div class="mt-6 overflow-hidden bg-white dark:bg-navy-900 shadow sm:rounded-lg">
            <div class="ui-card p-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $today['log_date'] }}</p>
                        @if ($today['shift_started'])
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Overnight shift started {{ $today['shift_started'] }}.</p>
                        @endif
                        <p class="mt-1 text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">{{ now()->format('h:i A') }}</p>
                        <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm text-slate-600 dark:text-slate-300">
                            <div>
                                <dt class="inline font-medium text-navy-900 dark:text-white">Schedule:</dt>
                                <dd class="inline">{{ $today['schedule'] ?? 'Not assigned' }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-navy-900 dark:text-white">Time in:</dt>
                                <dd class="inline">{{ $today['time_in'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-navy-900 dark:text-white">Time out:</dt>
                                <dd class="inline">{{ $today['time_out'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-navy-900 dark:text-white">Hours:</dt>
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
                            class="ui-btn-primary disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            Time In
                        </button>
                        <button
                            type="button"
                            wire:click="timeOut"
                            wire:loading.attr="disabled"
                            @disabled(! $today['can_record'] || $today['next'] !== 1)
                            class="ui-btn-secondary focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-100 dark:bg-navy-800 disabled:text-slate-400"
                        >
                            Time Out
                        </button>
                    </div>
                </div>

                @unless ($today['can_record'])
                    <p class="mt-4 text-sm text-rose-600 dark:text-rose-400">
                        {{ $today['locked'] ? 'This timesheet is locked.' : 'Attendance for this payroll period is closed.' }}
                    </p>
                @endunless

                <div class="mt-6">
                    <h4 class="text-sm font-medium text-navy-900 dark:text-white">Today's punches</h4>

                    @if (count($today['logs']) === 0)
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No punches recorded yet.</p>
                    @else
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($today['logs'] as $log)
                                <li class="inline-flex items-center rounded-full bg-slate-100 dark:bg-navy-800 px-3 py-1 text-sm text-slate-700 dark:text-slate-200">
                                    <span class="font-medium">{{ $log['label'] }}</span>
                                    <span class="ml-2">{{ $log['time'] }}</span>
                                    <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">{{ $log['source'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        Missing or wrong punch?
                        <a href="{{ route('portal.attendance.create') }}" class="font-medium text-teal-700 dark:text-teal-300 underline hover:text-teal-700 dark:text-teal-300">Request a correction</a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-col">
            <h4 class="text-sm font-medium text-navy-900 dark:text-white">Current payroll period ({{ $today['period'] }})</h4>

            @if (count($rows) === 0)
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Nothing here yet.</p>
            @else
                <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                        <div class="overflow-hidden shadow ring-1 ring-slate-900/5 dark:ring-white/10 md:rounded-lg">
                            <table class="ui-table">
                                <thead class="bg-slate-50 dark:bg-navy-800/40">
                                    <tr>
                                        <th scope="col" class="ui-th">Date</th>
                                        <th scope="col" class="ui-th">Day Type</th>
                                        <th scope="col" class="ui-th">Time In</th>
                                        <th scope="col" class="ui-th">Time Out</th>
                                        <th scope="col" class="ui-th">Punches</th>
                                        <th scope="col" class="ui-th">Hours</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-navy-800">
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td class="ui-td font-medium text-navy-900 dark:text-white">{{ $row['date'] }}</td>
                                            <td class="ui-td">
                                                {{ $row['day_type'] }}
                                                @if ($row['holiday'])
                                                    <span class="text-xs text-slate-400">({{ $row['holiday'] }})</span>
                                                @endif
                                            </td>
                                            <td class="ui-td">{{ $row['time_in'] ?? '—' }}</td>
                                            <td class="ui-td">{{ $row['time_out'] ?? '—' }}</td>
                                            <td class="ui-td">
                                                {{ $row['logs'] }}
                                                @if ($row['missing_punch'])
                                                    <span class="ml-1 text-xs font-medium text-rose-600 dark:text-rose-400">missing punch</span>
                                                @endif
                                            </td>
                                            <td class="ui-td">{{ $row['hours'] }}</td>
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
