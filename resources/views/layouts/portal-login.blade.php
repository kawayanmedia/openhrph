<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Employee sign in' }} · {{ portal_company_name() }}</title>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased dark:bg-navy-950 dark:text-slate-100">
    <div class="grid min-h-screen lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-navy-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-500/20 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-teal-400/10 blur-3xl"></div>

            <div class="relative">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-teal-300">Employee self-service</p>
                <h1 class="mt-4 max-w-md text-4xl font-bold leading-tight">{{ portal_company_name() }}</h1>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300">
                    File leaves, overtime and attendance requests, review your schedule and download your attendance record — all in one place.
                </p>
            </div>

            <ul class="relative space-y-3 text-sm text-slate-300">
                <li class="flex items-center gap-3"><span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span> Leave and attendance requests</li>
                <li class="flex items-center gap-3"><span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span> Timesheet and schedule visibility</li>
                <li class="flex items-center gap-3"><span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span> Payslip-ready payroll records</li>
            </ul>
        </section>

        <main class="flex items-center justify-center px-4 py-12 sm:px-6 lg:px-10">
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
