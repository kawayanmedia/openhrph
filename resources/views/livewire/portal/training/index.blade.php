<div class="space-y-6">
    <div class="ui-card p-5 sm:p-6">
        <h3 class="text-lg font-semibold text-navy-900 dark:text-white">My Training</h3>

        @if ($enrollments->isEmpty())
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">You are not enrolled in any training sessions.</p>
        @else
            <table class="ui-table mt-4">
                <thead>
                    <tr>
                        <th class="ui-th">Course</th>
                        <th class="ui-th">Schedule</th>
                        <th class="ui-th">Status</th>
                        <th class="ui-th text-right">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enrollments as $enrollment)
                        <tr>
                            <td class="ui-td">{{ $enrollment->session?->course?->title ?? '—' }}</td>
                            <td class="ui-td">{{ $enrollment->session?->starts_at?->toDayDateTimeString() ?? '—' }}</td>
                            <td class="ui-td">{{ str_replace('_', ' ', (string) $enrollment->status) }}</td>
                            <td class="ui-td text-right">{{ $enrollment->score ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="ui-card p-5 sm:p-6">
        <h3 class="text-lg font-semibold text-navy-900 dark:text-white">My Certifications</h3>

        @if ($certifications->isEmpty())
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No certifications on file.</p>
        @else
            <table class="ui-table mt-4">
                <thead>
                    <tr>
                        <th class="ui-th">Certification</th>
                        <th class="ui-th">Issuer</th>
                        <th class="ui-th">Issued</th>
                        <th class="ui-th">Expires</th>
                        <th class="ui-th">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($certifications as $certification)
                        <tr>
                            <td class="ui-td">{{ $certification->title }}</td>
                            <td class="ui-td">{{ $certification->issuer ?? '—' }}</td>
                            <td class="ui-td">{{ $certification->issued_at?->toFormattedDateString() ?? '—' }}</td>
                            <td class="ui-td">{{ $certification->expires_at?->toFormattedDateString() ?? '—' }}</td>
                            <td class="ui-td">{{ $certification->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
