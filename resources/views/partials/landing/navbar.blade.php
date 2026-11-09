<header
    x-data="{ mobileOpen: false }"
    class="sticky top-0 z-50 w-full border-b border-slate-200/80 bg-white/90 backdrop-blur-md transition-all"
>
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <!-- Logo -->
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-bold text-slate-900 transition hover:opacity-90">
            <span class="flex size-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="3" rx="4" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
            </span>
            <span class="text-xl tracking-tight text-slate-900 font-extrabold">Teamate</span>
        </a>

        <!-- Desktop Navigation Anchor Links -->
        <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
            <a href="#features" class="transition hover:text-emerald-600">Features</a>
            <a href="#permissions" class="transition hover:text-emerald-600">Permissions</a>
            <a href="#how-it-works" class="transition hover:text-emerald-600">How it works</a>
        </nav>

        <!-- Desktop Action Buttons -->
        <div class="hidden md:flex items-center gap-3">
            @auth
                <a
                    href="{{ route('dashboard') }}"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 hover:text-emerald-600 transition"
                >
                    Dashboard
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:text-emerald-600 transition"
                >
                    Log in
                </a>
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition"
                >
                    Get started
                </a>
            @endauth
        </div>

        <!-- Mobile Hamburger Button -->
        <div class="flex md:hidden">
            <button
                type="button"
                @click="mobileOpen = !mobileOpen"
                class="inline-flex items-center justify-center rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:outline-hidden"
                aria-label="Toggle Navigation Menu"
            >
                <svg x-show="!mobileOpen" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-cloak x-show="mobileOpen" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Drawer Menu -->
    <div
        x-cloak
        x-show="mobileOpen"
        @click.outside="mobileOpen = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="md:hidden border-b border-slate-200 bg-white px-4 pt-2 pb-6 space-y-3"
    >
        <div class="flex flex-col space-y-2 pt-2 pb-3 border-b border-slate-100 font-medium text-slate-700">
            <a @click="mobileOpen = false" href="#features" class="px-3 py-2 rounded-lg hover:bg-slate-50 hover:text-emerald-600">Features</a>
            <a @click="mobileOpen = false" href="#permissions" class="px-3 py-2 rounded-lg hover:bg-slate-50 hover:text-emerald-600">Permissions</a>
            <a @click="mobileOpen = false" href="#how-it-works" class="px-3 py-2 rounded-lg hover:bg-slate-50 hover:text-emerald-600">How it works</a>
        </div>
        <div class="flex flex-col gap-2 pt-2">
            @auth
                <a
                    href="{{ route('dashboard') }}"
                    class="w-full text-center rounded-lg border border-slate-300 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Dashboard
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="w-full text-center rounded-lg border border-slate-300 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Log in
                </a>
                <a
                    href="{{ route('register') }}"
                    class="w-full text-center rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700"
                >
                    Get started
                </a>
            @endauth
        </div>
    </div>
</header>

