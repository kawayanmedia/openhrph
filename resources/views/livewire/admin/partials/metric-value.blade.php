{{-- Renders a metric result. Expects: $value and optional $metric --}}
@php($component = isset($metric) ? $metric->component() : 'value')

@if ($component === 'trend' && is_array($value))
    @php($max = max(1, max($value ?: [0])))
    @php($recent = array_slice($value, -14, null, true))
    <div class="flex h-28 items-end gap-1">
        @foreach ($recent as $label => $count)
            <div class="group relative flex flex-1 flex-col items-center justify-end">
                <div class="w-full rounded-t bg-indigo-500/80 group-hover:bg-indigo-600"
                     style="height: {{ max(4, (int) round(((int) $count / $max) * 100)) }}%"></div>
                <span class="mt-1 hidden text-[10px] text-slate-400 group-hover:block">{{ $label }}</span>
                <span class="absolute -top-6 hidden rounded bg-slate-900 px-1.5 py-0.5 text-[10px] text-white group-hover:block">{{ $count }}</span>
            </div>
        @endforeach
    </div>
@elseif ($component === 'partition' && is_array($value))
    @php($total = max(1, array_sum(array_map('intval', $value))))
    <ul class="space-y-3">
        @foreach ($value as $label => $count)
            <li>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">{{ $label }}</span>
                    <span class="font-medium text-slate-900">{{ $count }}</span>
                </div>
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ (int) round(((int) $count / $total) * 100) }}%"></div>
                </div>
            </li>
        @endforeach
    </ul>
@elseif ($component === 'table' && is_array($value))
    <ul class="divide-y divide-slate-100">
        @foreach ($value as $row)
            @php($row = (array) $row)
            <li class="flex items-start gap-3 py-2">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $row['title'] ?? ($row['label'] ?? '') }}</p>
                    @if (! empty($row['subtitle']))
                        <p class="truncate text-xs text-slate-400">{{ $row['subtitle'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@elseif (is_array($value) || is_object($value))
    @php($value = (array) $value)
    <dl class="space-y-1">
        @foreach ($value as $label => $item)
            <div class="flex items-center justify-between gap-2">
                <dt class="text-sm text-slate-500">{{ is_array($item) ? ($item['label'] ?? $label) : $label }}</dt>
                <dd class="text-sm font-medium text-slate-900">{{ is_array($item) ? ($item['value'] ?? '') : $item }}</dd>
            </div>
        @endforeach
    </dl>
@else
    <span>{{ $value }}</span>
@endif
