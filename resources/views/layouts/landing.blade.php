<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Teamate - Digital Project Management with Granular RBAC' }}</title>
        <meta name="description" content="Teamate stores your projects securely, assigns clear permissions to every role, and backs up your data in a decentralized way.">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <!-- Inter Font -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Vite Assets & Tailwind CSS -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Alpine.js (Lightweight interactivity for navbar toggle & RBAC explorer) -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

        <style>
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            [x-cloak] {
                display: none !important;
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-50/60 text-slate-800 antialiased selection:bg-emerald-500 selection:text-white">
        <!-- Sticky Navigation Bar -->
        @include('partials.landing.navbar')

        <!-- Main Content -->
        <main>
            @yield('content')
        </main>

        <!-- Footer -->
        @include('partials.landing.footer')
    </body>
</html>

