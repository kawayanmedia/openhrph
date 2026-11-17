<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin' }} · {{ config('app.name') }}</title>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full antialiased">
    <div class="flex min-h-full"
         x-data="{ sidebarOpen: false, assistantOpen: false }"
         @keydown.window.meta.k.prevent="assistantOpen = true"
         @keydown.window.ctrl.k.prevent="assistantOpen = true">
        <div x-show="sidebarOpen" x-transition.opacity
             class="fixed inset-0 z-30 bg-navy-950/60 backdrop-blur-sm lg:hidden"
             @click="sidebarOpen = false"></div>

        <div class="fixed inset-y-0 left-0 z-40 w-64 transform transition-transform duration-200 lg:static lg:translate-x-0"
             :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
            @include('livewire.admin.partials.sidebar')
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-navy-800 dark:bg-navy-950/90">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-navy-700 dark:text-slate-300 dark:hover:bg-navy-800 lg:hidden"
                                @click="sidebarOpen = true" aria-label="Open navigation">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>

                        <nav aria-label="Breadcrumb" class="min-w-0">
                            <ol class="flex items-center gap-2 text-sm">
                                <li class="hidden text-slate-500 dark:text-slate-400 sm:block">Workspace</li>
                                <li class="hidden text-slate-300 dark:text-navy-700 sm:block">/</li>
                                <li class="truncate font-semibold text-navy-900 dark:text-white">{{ $header ?? $title ?? '' }}</li>
                            </ol>
                        </nav>
                    </div>

                    <div class="flex items-center gap-2">
                        @unless (request()->routeIs('admin.assistant'))
                            <button type="button" @click="assistantOpen = true"
                                    class="ui-btn-secondary !px-2.5 !py-1.5 text-xs"
                                    title="Ask the HR assistant (⌘K)">
                                <svg class="h-4 w-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 4.5c.53 2.06 2.127 3.657 4.187 4.187-2.06.53-3.657 2.127-4.187 4.187-.53-2.06-2.127-3.657-4.187-4.187 2.06-.53 3.657-2.127 4.187-4.187ZM18 13.5c.31 1.203 1.297 2.19 2.5 2.5-1.203.31-2.19 1.297-2.5 2.5-.31-1.203-1.297-2.19-2.5-2.5 1.203-.31 2.19-1.297 2.5-2.5ZM6.75 16.5c.207.803.947 1.543 1.75 1.75-.803.207-1.543.947-1.75 1.75-.207-.803-.947-1.543-1.75-1.75.803-.207 1.543-.947 1.75-1.75Z" />
                                </svg>
                                <span class="hidden sm:inline">Ask AI</span>
                            </button>
                        @endunless

                        <button type="button"
                                onclick="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-navy-800"
                                aria-label="Toggle dark mode">
                            <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15A9.75 9.75 0 1 1 9 2.25a7.5 7.5 0 0 0 12.75 12.75Z" />
                            </svg>
                            <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21m9-9h-2.25M5.25 12H3m15.36-6.36-1.6 1.6M7.24 16.76l-1.6 1.6m12.72 0-1.6-1.6M7.24 7.24l-1.6-1.6M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                        </button>

                        @auth
                            <livewire:admin.company-switcher />

                            <details class="relative">
                                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-navy-800 [&::-webkit-details-marker]:hidden">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-800 dark:bg-teal-500/20 dark:text-teal-300">
                                        {{ strtoupper(substr(auth()->user()->name ?? auth()->user()->email ?? 'U', 0, 1)) }}
                                    </span>
                                    <span class="hidden max-w-40 truncate sm:block">{{ auth()->user()->name ?? auth()->user()->email }}</span>
                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </summary>
                                <div class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-panel dark:border-navy-700 dark:bg-navy-900">
                                    <div class="border-b border-slate-100 px-4 py-2 dark:border-navy-800">
                                        <p class="truncate text-sm font-semibold text-navy-900 dark:text-white">{{ auth()->user()->name ?? auth()->user()->email }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                                    </div>
                                    <a href="{{ route('admin.resource.edit', ['resource' => 'change-password', 'resourceId' => auth()->id()]) }}"
                                       class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-navy-800">Change password</a>
                                    <form method="POST" action="{{ route('admin.logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">Sign out</button>
                                    </form>
                                </div>
                            </details>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="flex-1">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    @foreach (['admin.saved' => 'emerald', 'admin.action' => 'emerald', 'admin.error' => 'rose'] as $flash => $tone)
                        @if (session($flash))
                            <div x-data="{ show: true }" x-show="show" x-transition
                                 class="mb-4 flex items-start justify-between gap-3 rounded-xl border px-4 py-3 text-sm
                                        {{ $tone === 'rose' ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300' }}">
                                <span>{{ session($flash) }}</span>
                                <button type="button" class="opacity-60 hover:opacity-100" @click="show = false" aria-label="Dismiss">&times;</button>
                            </div>
                        @endif
                    @endforeach

                    {{ $slot }}
                </div>
            </main>
        </div>

        @auth
            @unless (request()->routeIs('admin.assistant'))
                <div x-show="assistantOpen" x-cloak x-transition.opacity
                     class="fixed inset-0 z-40 bg-navy-950/50 backdrop-blur-sm"
                     @click="assistantOpen = false"
                     aria-hidden="true"></div>

                <aside x-show="assistantOpen" x-cloak
                       x-transition:enter="transition ease-out duration-200"
                       x-transition:enter-start="translate-x-full"
                       x-transition:enter-end="translate-x-0"
                       x-transition:leave="transition ease-in duration-150"
                       x-transition:leave-start="translate-x-0"
                       x-transition:leave-end="translate-x-full"
                       @keydown.escape.window="assistantOpen = false"
                       x-on:assistant-close.window="assistantOpen = false"
                       class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-navy-800 dark:bg-navy-950"
                       role="dialog" aria-modal="true" aria-label="HR assistant">
                    <livewire:admin.assistant.chat variant="panel" />
                </aside>
            @endunless
        @endauth
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
