<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">A snapshot of your organization.</p>
        </div>
        <p class="text-sm text-slate-400">{{ now()->format('l, M d, Y') }}</p>
    </div>

    @if (! $readiness['is_ready'])
        <div class="mb-6 flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
            <div class="flex min-w-0 flex-1 items-center gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-50 text-sm font-semibold text-teal-700">
                    {{ $readiness['percent'] }}%
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900">Finish setting up payroll</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $readiness['required_complete'] }} of {{ $readiness['required_total'] }} required steps complete
                        @if ($readiness['required_pending'] > 0)
                            · {{ $readiness['required_pending'] }} remaining before you can run payroll
                        @endif
                    </p>
                    <div class="mt-2 h-1.5 w-full max-w-md overflow-hidden rounded-full bg-slate-100"
                         role="progressbar" aria-valuenow="{{ $readiness['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-teal-500" style="width: {{ $readiness['percent'] }}%"></div>
                    </div>
                </div>
            </div>

            <a href="{{ route('admin.getting-started') }}" class="ui-btn-primary shrink-0">
                Continue setup
            </a>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($metrics as $metric)
            @php($component = $metric->component())
            <div @class([
                'rounded-lg border border-slate-200 bg-white p-5 shadow-sm',
                'lg:col-span-2' => in_array($component, ['trend', 'partition', 'table'], true),
            ])>
                <div class="text-sm font-medium text-slate-500">{{ $metric->name() }}</div>
                <div class="{{ $component === 'value' ? 'mt-2 text-3xl font-semibold text-slate-900' : 'mt-4' }}">
                    @include('livewire.admin.partials.metric-value', ['value' => $metric->calculate(request()), 'metric' => $metric])
                </div>
            </div>
        @endforeach
    </div>
</div>
