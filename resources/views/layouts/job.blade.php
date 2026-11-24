<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Careers' }} · {{ portal_company_name() }}</title>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased dark:bg-navy-950 dark:text-slate-100">
    <div class="flex min-h-screen flex-col">
        @include('livewire.job.nav')

        <main class="flex-1">
            <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white dark:border-navy-800 dark:bg-navy-950">
            <div class="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 dark:text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <span>&copy; {{ now()->year }} {{ portal_company_name() }}.</span>
                <a href="{{ route('portal.login') }}" class="font-medium text-teal-700 hover:underline dark:text-teal-300">Employee sign in</a>
            </div>
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
