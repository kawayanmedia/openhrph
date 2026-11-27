<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">Workforce analytics</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Headcount, separation and retirement forecasts, learning completion and compliance exposure as of {{ $summary['as_of'] ?? '' }}.
            </p>
        </div>

        <button type="button" wire:click="refreshMetrics" class="ui-btn-secondary">Refresh</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Active headcount', 'value' => $summary['active_headcount'] ?? 0],
            ['label' => 'New hires (90 days)', 'value' => $summary['new_hires_90_days'] ?? 0],
            ['label' => 'Separations YTD', 'value' => $summary['separations_ytd'] ?? 0],
            ['label' => 'Pending retirements', 'value' => $summary['pending_retirements'] ?? 0],
            ['label' => 'Retirements next 12 months', 'value' => $summary['retirement_forecast_12_months'] ?? 0],
            ['label' => 'Training completion', 'value' => ($summary['training_completion_rate'] ?? 0).'%'],
            ['label' => 'Reviews finalized', 'value' => ($summary['performance_completion_rate'] ?? 0).'%'],
            ['label' => 'Pending corrections', 'value' => $summary['pending_attendance_corrections'] ?? 0],
            ['label' => 'Open external claims', 'value' => $summary['open_external_claims'] ?? 0],
            ['label' => 'Certifications expiring', 'value' => $summary['expiring_certifications_30_days'] ?? 0],
            ['label' => 'Approved compliance rules', 'value' => $summary['approved_compliance_rules'] ?? 0],
            ['label' => 'Rules expiring (90 days)', 'value' => $summary['compliance_rules_expiring_90_days'] ?? 0],
        ] as $metric)
            <div class="ui-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-navy-900 dark:text-white">{{ $metric['value'] }}</p>
            </div>
        @endforeach
    </div>
</div>
