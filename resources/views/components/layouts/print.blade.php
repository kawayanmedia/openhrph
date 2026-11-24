<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Print' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .print-sheet { box-shadow: none !important; border: 0 !important; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>
<body class="min-h-full bg-slate-100 text-slate-900">
    <div class="mx-auto max-w-5xl px-4 py-8 print:max-w-none print:p-0">
        {{ $slot }}
    </div>
</body>
</html>
