<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Employee Portal' }} · {{ portal_company_name() }}</title>
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
        @include('livewire.portal.nav')

        <main class="flex-1">
            <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @foreach (['status' => 'emerald', 'success' => 'emerald', 'error' => 'rose'] as $flash => $tone)
                    @if (session($flash))
                        <div class="mb-4 flex items-start justify-between gap-3 rounded-xl border px-4 py-3 text-sm {{ $tone === 'rose' ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300' }}">
                            <span>{{ session($flash) }}</span>
                        </div>
                    @endif
                @endforeach

                @if (isset($header))
                    <div class="mb-6">
                        <h1 class="text-2xl font-semibold tracking-tight text-navy-900 dark:text-white">{{ $header }}</h1>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white dark:border-navy-800 dark:bg-navy-950">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 dark:text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <span>&copy; {{ now()->year }} {{ portal_company_name() }}. All rights reserved.</span>
                <span>Employee self-service · {{ config('app.name') }}</span>
            </div>
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
