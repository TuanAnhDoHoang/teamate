<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-50/70 antialiased selection:bg-emerald-500 selection:text-white dark:bg-neutral-950 dark:text-slate-100">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="group flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md transition group-hover:scale-105">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="4" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </span>
                    <span class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Teamate</span>
                </a>

                <div class="rounded-2xl border border-slate-200/90 bg-white p-8 shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
