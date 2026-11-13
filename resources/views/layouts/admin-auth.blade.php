<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sign in' }} · {{ config('app.name') }}</title>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-navy-950 dark:text-slate-100">
    <div class="flex min-h-full">
        <aside class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-navy-900 px-12 py-14 text-white lg:flex">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, #14b8a6 0, transparent 45%), radial-gradient(circle at 80% 0%, #2563eb 0, transparent 40%);"></div>

            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-500/20 text-lg font-bold text-teal-300">
                        {{ strtoupper(substr(config('app.name'), 0, 1)) }}
                    </span>
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </div>

                <h1 class="mt-16 max-w-md text-4xl font-semibold leading-tight tracking-tight">
                    Run payroll with confidence.
                </h1>
                <p class="mt-4 max-w-md text-base text-slate-300">
                    Philippine statutory compliance, attendance-driven earnings and payslips — in one workspace for your people and payroll teams.
                </p>

                <ul class="mt-10 space-y-4 text-sm text-slate-200">
                    @foreach (['SSS, PhilHealth, Pag-IBIG and BIR computed automatically', 'Guided payroll runs with preview and approval', 'Payslips, registers and remittance reports'] as $feature)
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-teal-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-slate-400">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </aside>

        <main class="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-16">
            <div class="mx-auto w-full max-w-sm">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
