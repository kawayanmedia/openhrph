<div class="sm:col-span-6 border-b border-slate-200 pb-3 dark:border-navy-800">
    <h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ $field->name }}</h3>
    @if ($field->help)
        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{!! $field->help !!}</p>
    @endif
</div>
