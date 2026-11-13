@php
    $sections = \App\Livewire\Admin\Support\Navigation::sections();
    $current = fn (string $url) => request()->is(trim($url, '/').'*');
@endphp

<aside class="flex h-full w-full flex-col bg-navy-950 text-slate-200 ring-1 ring-white/5">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-4">
        <a href="{{ url(config('admin.path')) }}" class="flex items-center gap-3 overflow-hidden">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-sm font-bold text-teal-300">
                {{ strtoupper(substr(config('app.name'), 0, 1)) }}
            </span>
            <span class="truncate text-sm font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
        </a>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
        @foreach ($sections as $section)
            @if (isset($section['url']))
                <a href="{{ $section['url'] }}"
                   class="ui-nav-link {{ $current($section['url']) ? 'ui-nav-link-active' : 'ui-nav-link-idle' }}"
                   title="{{ $section['label'] }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span>
                    <span class="truncate">{{ $section['label'] }}</span>
                </a>
                @continue
            @endif

            <div class="pt-4 first:pt-0">
                <p class="px-3 pb-1 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-500">
                    {{ $section['label'] }}
                </p>

                @foreach ($section['items'] as $item)
                    <a href="{{ $item['url'] }}"
                       class="ui-nav-link {{ $current($item['url']) ? 'ui-nav-link-active' : 'ui-nav-link-idle' }}"
                       title="{{ $item['label'] }}">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach

                @foreach ($section['groups'] as $group)
                    <p class="px-3 pb-1 pt-3 text-[0.65rem] font-medium uppercase tracking-wide text-slate-600">{{ $group['label'] }}</p>

                    @foreach ($group['items'] as $item)
                        <a href="{{ $item['url'] }}"
                           class="ui-nav-link {{ $current($item['url']) ? 'ui-nav-link-active' : 'ui-nav-link-idle' }}"
                           title="{{ $item['label'] }}">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-600"></span>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endforeach

                @foreach ($section['trailing'] as $item)
                    <a href="{{ $item['url'] }}"
                       class="ui-nav-link {{ $current($item['url']) ? 'ui-nav-link-active' : 'ui-nav-link-idle' }}"
                       title="{{ $item['label'] }}">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-500"></span>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="border-t border-white/5 p-3">
        <div class="rounded-xl bg-white/5 px-3 py-3">
            <p class="text-xs font-semibold text-white">Payroll engine</p>
            <p class="mt-1 text-[0.7rem] leading-relaxed text-slate-400">Go engine · Philippine statutory rates</p>
        </div>
    </div>
</aside>
