<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="ui-card p-5 sm:p-6">
        <h3 class="text-lg font-semibold text-navy-900 dark:text-white">Performance Reviews</h3>

        @if ($reviews->isEmpty())
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No reviews have been started for you yet.</p>
        @else
            <table class="ui-table mt-4">
                <thead>
                    <tr>
                        <th class="ui-th">Cycle</th>
                        <th class="ui-th">Reviewer</th>
                        <th class="ui-th">Status</th>
                        <th class="ui-th text-right">Overall</th>
                        <th class="ui-th text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reviews as $review)
                        <tr>
                            <td class="ui-td">{{ $review->cycle?->name ?? '—' }}</td>
                            <td class="ui-td">{{ $review->reviewer?->fullname ?? '—' }}</td>
                            <td class="ui-td">{{ str_replace('_', ' ', (string) $review->status) }}</td>
                            <td class="ui-td text-right">{{ $review->overall_rating ?? '—' }}</td>
                            <td class="ui-td text-right">
                                @if ($review->finalized_at && ! $review->acknowledged_at)
                                    <button type="button" wire:click="acknowledge({{ $review->getKey() }})" class="ui-btn-primary">
                                        Acknowledge
                                    </button>
                                @elseif ($review->acknowledged_at)
                                    <span class="text-sm text-slate-500 dark:text-slate-400">Acknowledged</span>
                                @else
                                    <span class="text-sm text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="ui-card p-5 sm:p-6">
        <h3 class="text-lg font-semibold text-navy-900 dark:text-white">My Goals</h3>

        @if ($goals->isEmpty())
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No goals have been set.</p>
        @else
            <table class="ui-table mt-4">
                <thead>
                    <tr>
                        <th class="ui-th">Goal</th>
                        <th class="ui-th">Cycle</th>
                        <th class="ui-th">Target</th>
                        <th class="ui-th text-right">Weight</th>
                        <th class="ui-th">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($goals as $goal)
                        <tr>
                            <td class="ui-td">{{ $goal->title }}</td>
                            <td class="ui-td">{{ $goal->cycle?->name ?? '—' }}</td>
                            <td class="ui-td">{{ $goal->target ?? '—' }}</td>
                            <td class="ui-td text-right">{{ $goal->weight ?? '—' }}</td>
                            <td class="ui-td">{{ str_replace('_', ' ', (string) $goal->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="ui-card p-5 sm:p-6">
        <h3 class="text-lg font-semibold text-navy-900 dark:text-white">Development Plans</h3>

        @if ($plans->isEmpty())
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No development plans on file.</p>
        @else
            <table class="ui-table mt-4">
                <thead>
                    <tr>
                        <th class="ui-th">Plan</th>
                        <th class="ui-th">Cycle</th>
                        <th class="ui-th">Due</th>
                        <th class="ui-th">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plans as $plan)
                        <tr>
                            <td class="ui-td">{{ $plan->title }}</td>
                            <td class="ui-td">{{ $plan->cycle?->name ?? '—' }}</td>
                            <td class="ui-td">{{ $plan->due_date?->toFormattedDateString() ?? '—' }}</td>
                            <td class="ui-td">{{ $plan->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
