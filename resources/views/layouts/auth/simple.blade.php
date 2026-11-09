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
    <body class="min-h-screen bg-slate-50/70 text-slate-800 antialiased selection:bg-emerald-500 selection:text-white dark:bg-neutral-950 dark:text-slate-100">
        <div class="relative flex min-h-screen flex-col items-center justify-center p-4 sm:p-6 lg:p-8">
            <!-- Subtle background accent blob -->
            <div class="pointer-events-none absolute inset-0 -z-10 flex items-center justify-center overflow-hidden">
                <div class="h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl dark:bg-emerald-600/10"></div>
            </div>

            <!-- Top Brand & Return Home Link -->
            <div class="mb-6 flex flex-col items-center gap-3">
                <a href="{{ route('home') }}" class="group flex items-center gap-2.5 transition hover:opacity-95" wire:navigate>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md transition group-hover:scale-105">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="4" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </span>
                    <span class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Teamate</span>
                </a>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50/80 px-3 py-0.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Secure RBAC Project Management
                </span>
            </div>

            <!-- Main Auth Card -->
            <div class="w-full max-w-md">
                <div class="rounded-2xl border border-slate-200/90 bg-white p-7 shadow-xl dark:border-neutral-800 dark:bg-neutral-900 sm:p-9">
                    {{ $slot }}
                </div>

                <!-- Card Footer: Return to Home -->
                <div class="mt-6 text-center">
                    <a
                        href="{{ route('home') }}"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-emerald-600 dark:text-neutral-400 dark:hover:text-emerald-400"
                        wire:navigate
                    >
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                        Back to Teamate home
                    </a>
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
