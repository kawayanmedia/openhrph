{{--
    Renders a field value for index/detail.
    Expects: $field (App\Livewire\Admin\Fields\Field) and $model (Illuminate\Database\Eloquent\Model)
--}}
@php($value = $field->resolveForDisplay($model, $field->attribute))

@switch($field->type())
    @case('boolean')
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $value ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-300' }}">
            {{ $value ? 'Yes' : 'No' }}
        </span>
        @break

    @case('badge')
        @php($color = $field->map[$value] ?? null)
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $color ? $field->colorClasses($color) : 'bg-slate-100 text-slate-800 dark:bg-navy-800 dark:text-slate-200' }}">
            {{ is_scalar($value) ? $value : '' }}
        </span>
        @break

    @case('status')
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-800 dark:bg-navy-800 dark:text-slate-200">
            {{ $field->options[$value] ?? $value }}
        </span>
        @break

    @case('currency')
        <span>{{ number_format((float) $value, 2) }}</span>
        @break

    @case('stack')
        @foreach ($field->lines as $line)
            <div class="{{ $loop->first ? 'font-medium text-slate-900' : 'text-xs text-slate-500' }}">
                {{ $line->resolveForDisplay($model, $line->attribute) }}
            </div>
        @endforeach
        @break

    @case('gravatar')
        <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($model->email ?? ''))) }}?s={{ $field->maxWidth }}"
             class="rounded-full" alt="">
        @break

    @case('file')
        @if ($value)
            <a href="{{ \Illuminate\Support\Facades\Storage::disk($field->disk ?? 'public')->url($value) }}"
               target="_blank" rel="noopener"
               class="ui-link underline">Download</a>
        @else
            <span class="text-slate-400 dark:text-slate-500">—</span>
        @endif
        @break

    @case('files')
        @if (! empty($value))
            <ul class="space-y-1">
                @foreach ($value as $file)
                    <li>
                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener"
                           class="ui-link underline">{{ $file['file_name'] ?? $file['name'] }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            <span class="text-slate-400 dark:text-slate-500">—</span>
        @endif
        @break

    @default
        @if ($field->asHtml)
            <span>{!! $value !!}</span>
        @elseif (is_array($value))
            <span>{{ implode(', ', \Illuminate\Support\Arr::flatten($value)) }}</span>
        @else
            <span>{{ $value }}</span>
        @endif
@endswitch
