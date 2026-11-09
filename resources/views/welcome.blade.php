@extends('layouts.landing')

@section('content')
    <!-- 2.2 Hero Section -->
    <section class="relative overflow-hidden py-16 lg:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-8">
                <!-- Hero Left: Copy & CTAs -->
                <div class="lg:col-span-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50/80 px-3.5 py-1 text-xs font-semibold text-emerald-800">
                        <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Next-Gen RBAC Project Security
                    </div>

                    <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl lg:leading-[1.12]">
                        Team project management: the right people, the right access
                    </h1>

                    <p class="mt-6 text-lg leading-relaxed text-slate-600 sm:text-xl">
                        Teamate stores your projects securely, assigns clear permissions to every role, and backs up your data in a decentralized way.
                    </p>

                    <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row lg:justify-start">
                        <a
                            href="{{ route('register') }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-xl bg-emerald-600 px-6 py-3.5 text-base font-semibold text-white shadow-md hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition"
                        >
                            Get started
                        </a>
                        <a
                            href="#permissions"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-base font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition"
                        >
                            View demo
                        </a>
                    </div>
                </div>

                <!-- Hero Right: Interactive UI Mockup with Role Badges -->
                <div class="lg:col-span-6">
                    <div class="relative mx-auto max-w-lg rounded-2xl border border-slate-200/90 bg-white shadow-xl lg:max-w-none overflow-hidden">
                        <!-- Mockup Window Header -->
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <span class="size-3 rounded-full bg-rose-400"></span>
                                <span class="size-3 rounded-full bg-amber-400"></span>
                                <span class="size-3 rounded-full bg-emerald-400"></span>
                            </div>
                            <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                <svg class="size-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                teamate.app/workspace/main
                            </div>
                            <span class="inline-flex items-center rounded-md bg-emerald-100/70 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                RBAC Enforced
                            </span>
                        </div>

                        <!-- Mockup Body Content -->
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h2 class="text-base font-bold text-slate-900">Security Core Engine</h2>
                                    <p class="text-xs text-slate-500">Active repository with hierarchical permission rules</p>
                                </div>
                                <span class="text-xs font-mono text-slate-400 bg-slate-100 px-2 py-1 rounded">main</span>
                            </div>

                            <!-- Mock items with colored role badges -->
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-slate-800">Folder A / source-code</p>
                                            <p class="text-[11px] text-slate-500">Kernel crypto & Vault integrations</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-badge color="red">Admin</x-badge>
                                        <x-badge color="blue">Dev Team</x-badge>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 items-center justify-center rounded-lg bg-purple-100 text-purple-700">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-slate-800">Folder B / review-docs</p>
                                            <p class="text-[11px] text-slate-500">Security pentest reports</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-badge color="purple">Review Team</x-badge>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-slate-800">Folder C / client-shared</p>
                                            <p class="text-[11px] text-slate-500">Milestone deliverables</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-badge color="green">Client</x-badge>
                                    </div>
                                </div>
                            </div>

                            <!-- Decentralized node info banner -->
                            <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3.5 py-2 text-xs text-emerald-800">
                                <span class="flex items-center gap-1.5 font-medium">
                                    <svg class="size-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                        <path d="m9 12 2 2 4-4" />
                                    </svg>
                                    Decentralized backup active
                                </span>
                                <span class="font-mono text-[11px] text-emerald-700">3 Node Replicas</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2.3 Features Section (Exactly 3 cards) -->
    <section id="features" class="border-t border-slate-200/80 bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Engineered for high-trust collaboration
                </h2>
                <p class="mt-4 text-base text-slate-600 sm:text-lg">
                    Three pillars combine to protect project integrity, prevent unauthorized access, and ensure high data durability.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                @foreach ($features as $feature)
                    <x-card :highlight="$feature['highlight']" class="flex flex-col justify-between">
                        <div>
                            <!-- Icon selection per feature -->
                            <div @class([
                                'mb-6 flex size-12 items-center justify-center rounded-xl',
                                'bg-slate-100 text-slate-800' => ! $feature['highlight'],
                                'bg-emerald-600 text-white shadow-xs' => $feature['highlight'],
                            ])>
                                @if ($feature['id'] === 'storage')
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                    </svg>
                                @elseif ($feature['id'] === 'permissions')
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                @else
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <h3 class="text-xl font-bold text-slate-900">{{ $feature['title'] }}</h3>
                                @if ($feature['highlight'])
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                        Decentralized
                                    </span>
                                @endif
                            </div>

                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                {{ $feature['description'] }}
                            </p>
                        </div>
                    </x-card>
                @endforeach
            </div>
        </div>
    </section>

    <!-- 2.4 RBAC Permissions Section (Main Highlight with Alpine.js Interaction) -->
    <section id="permissions" class="py-20 lg:py-28 bg-slate-50/70">
        <div
            x-data="{
                selectedRole: 'admin',
                matrix: @js($matrix),
                getPermissions(roleId, resId) {
                    return (this.matrix[roleId] && this.matrix[roleId][resId]) ? this.matrix[roleId][resId] : [];
                },
                hasPermission(roleId, resId) {
                    return this.getPermissions(roleId, resId).length > 0;
                },
                hasSpecificPermission(roleId, resId, perm) {
                    return this.getPermissions(roleId, resId).includes(perm);
                }
            }"
            class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
        >
            <div class="text-center max-w-3xl mx-auto mb-12">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1 text-xs font-semibold text-slate-700 shadow-2xs mb-4">
                    Core Security Engine
                </div>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Hierarchical Role-Based Access Control
                </h2>
                <p class="mt-4 text-base text-slate-600 sm:text-lg">
                    Teamate enforces 3 precise permission levels: <strong>Read</strong>, <strong>Edit</strong>, and <strong>Own</strong>. Select a role below to preview how rights inherit downward through your workspace hierarchy.
                </p>
            </div>

            <!-- Role Selector Buttons (4 roles) -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-10">
                @foreach ($roles as $roleKey => $role)
                    <button
                        type="button"
                        @click="selectedRole = '{{ $roleKey }}'"
                        :class="selectedRole === '{{ $roleKey }}'
                            ? 'border-emerald-600 bg-white shadow-md ring-2 ring-emerald-600/20'
                            : 'border-slate-200 bg-white/70 hover:bg-white hover:border-slate-300 text-slate-600'"
                        class="flex flex-col items-start rounded-xl border p-4 text-left transition-all duration-150 focus:outline-hidden"
                    >
                        <div class="flex w-full items-center justify-between mb-2">
                            <span class="text-sm font-bold text-slate-900">{{ $role['name'] }}</span>
                            <x-badge :color="$role['color']">{{ $role['badge'] ?? $role['name'] }}</x-badge>
                        </div>
                        <p class="text-xs text-slate-500 line-clamp-2">{{ $role['description'] }}</p>
                    </button>
                @endforeach
            </div>

            <!-- Desktop View: Side-by-side Table & Folder Tree -->
            <div class="hidden lg:grid lg:grid-cols-12 gap-8 items-start">
                <!-- Left Column: Permission Matrix Table -->
                <div class="lg:col-span-6 rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="size-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h3 class="text-sm font-bold text-slate-900">Permission Matrix</h3>
                        </div>
                        <span class="text-xs text-slate-500 font-medium">Inherits downward</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50/50 text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-3.5">Resource Node</th>
                                    @foreach ($permissionTypes as $perm)
                                        <th class="px-3 py-3.5 text-center">{{ $perm }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($resources as $res)
                                    <tr
                                        :class="hasPermission(selectedRole, '{{ $res['id'] }}')
                                            ? 'bg-emerald-50/30 font-medium'
                                            : 'opacity-50 bg-slate-50/40'"
                                        class="transition-colors duration-150"
                                    >
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2" style="padding-left: {{ $res['level'] * 12 }}px">
                                                @if ($res['type'] === 'Team')
                                                    <svg class="size-4 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                    </svg>
                                                @elseif ($res['type'] === 'Project')
                                                    <svg class="size-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                    </svg>
                                                @else
                                                    <svg class="size-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                    </svg>
                                                @endif
                                                <span class="text-xs sm:text-sm text-slate-900">{{ $res['name'] }}</span>
                                            </div>
                                        </td>
                                        @foreach ($permissionTypes as $perm)
                                            <td class="px-3 py-4 text-center">
                                                <template x-if="hasSpecificPermission(selectedRole, '{{ $res['id'] }}', '{{ $perm }}')">
                                                    <span class="inline-flex size-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="!hasSpecificPermission(selectedRole, '{{ $res['id'] }}', '{{ $perm }}')">
                                                    <span class="inline-block size-1.5 rounded-full bg-slate-300"></span>
                                                </template>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right Column: Interactive Folder Tree with Chips & Dynamic States -->
                <div class="lg:col-span-6 rounded-2xl border border-slate-200 bg-white shadow-xs p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <h3 class="text-sm font-bold text-slate-900">Resource Tree Explorer</h3>
                        </div>
                        <span class="text-xs text-slate-400">Live role evaluation</span>
                    </div>

                    <div class="space-y-3 font-mono text-xs">
                        @foreach ($resources as $res)
                            <div
                                x-show="!(selectedRole === 'client' && !hasPermission(selectedRole, '{{ $res['id'] }}'))"
                                :class="hasPermission(selectedRole, '{{ $res['id'] }}')
                                    ? 'border-slate-200 bg-slate-50/70 text-slate-900'
                                    : 'border-dashed border-slate-200 opacity-45 bg-slate-50/30 text-slate-400'"
                                class="flex items-center justify-between rounded-xl border p-3 transition-all duration-150"
                                style="margin-left: {{ $res['level'] * 18 }}px"
                            >
                                <div class="flex items-center gap-2.5">
                                    <template x-if="hasPermission(selectedRole, '{{ $res['id'] }}')">
                                        <svg class="size-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                    </template>
                                    <template x-if="!hasPermission(selectedRole, '{{ $res['id'] }}')">
                                        <svg class="size-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </template>
                                    <span class="font-sans font-medium text-xs sm:text-sm">{{ $res['name'] }}</span>
                                </div>

                                <!-- Permission Chips -->
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <template x-if="!hasPermission(selectedRole, '{{ $res['id'] }}')">
                                        <span class="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-[11px] font-sans font-medium text-slate-500">
                                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            No Access
                                        </span>
                                    </template>
                                    <template x-for="perm in getPermissions(selectedRole, '{{ $res['id'] }}')" :key="perm">
                                        <span
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border-emerald-200': perm === 'Read',
                                                'bg-blue-50 text-blue-700 border-blue-200': perm === 'Edit',
                                                'bg-purple-50 text-purple-700 border-purple-200': perm === 'Own'
                                            }"
                                            class="rounded border px-2 py-0.5 font-sans text-[11px] font-semibold"
                                            x-text="perm"
                                        ></span>
                                    </template>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Mobile View: Collapsed Folder Tree only (Table hidden per requirements) -->
            <div class="block lg:hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-sm font-bold text-slate-900">Resource Permissions</h3>
                    <span class="text-xs text-emerald-600 font-semibold" x-text="'Role: ' + selectedRole.toUpperCase()"></span>
                </div>

                <div class="space-y-3">
                    @foreach ($resources as $res)
                        <div
                            x-show="!(selectedRole === 'client' && !hasPermission(selectedRole, '{{ $res['id'] }}'))"
                            :class="hasPermission(selectedRole, '{{ $res['id'] }}')
                                ? 'border-slate-200 bg-slate-50/70'
                                : 'border-dashed border-slate-200 opacity-45 bg-slate-50/30'"
                            class="flex flex-col gap-2 rounded-xl border p-3 transition"
                            style="margin-left: {{ $res['level'] * 10 }}px"
                        >
                            <div class="flex items-center gap-2">
                                <template x-if="hasPermission(selectedRole, '{{ $res['id'] }}')">
                                    <svg class="size-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                </template>
                                <template x-if="!hasPermission(selectedRole, '{{ $res['id'] }}')">
                                    <svg class="size-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </template>
                                <span class="text-xs font-semibold text-slate-900">{{ $res['name'] }}</span>
                            </div>

                            <div class="flex flex-wrap gap-1.5 pl-6">
                                <template x-if="!hasPermission(selectedRole, '{{ $res['id'] }}')">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500 font-medium">
                                        No Access
                                    </span>
                                </template>
                                <template x-for="perm in getPermissions(selectedRole, '{{ $res['id'] }}')" :key="perm">
                                    <span
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border-emerald-200': perm === 'Read',
                                            'bg-blue-50 text-blue-700 border-blue-200': perm === 'Edit',
                                            'bg-purple-50 text-purple-700 border-purple-200': perm === 'Own'
                                        }"
                                        class="rounded border px-2 py-0.5 text-[11px] font-semibold"
                                        x-text="perm"
                                    ></span>
                                </template>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- 2.5 How It Works Section (3 steps connected by line/arrows) -->
    <section id="how-it-works" class="border-t border-slate-200/80 bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Get up and running in three simple steps
                </h2>
                <p class="mt-4 text-base text-slate-600 sm:text-lg">
                    Start securing your team's code, documents and assets with instant policy enforcement.
                </p>
            </div>

            <div class="relative grid grid-cols-1 gap-12 md:grid-cols-3 md:gap-8">
                <!-- Connecting Line on Desktop -->
                <div class="hidden md:block absolute top-12 left-1/6 right-1/6 h-0.5 bg-slate-200 -z-0" aria-hidden="true"></div>

                @foreach ($steps as $index => $step)
                    <div class="relative z-10 flex flex-col items-center text-center">
                        <!-- Step Number & Icon Badge -->
                        <div class="relative mb-6 flex size-24 items-center justify-center rounded-2xl border-2 border-emerald-600 bg-white shadow-md">
                            <span class="absolute -top-3 -right-3 flex size-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white shadow-xs">
                                {{ $step['step'] }}
                            </span>
                            @if ($step['step'] === 1)
                                <svg class="size-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            @elseif ($step['step'] === 2)
                                <svg class="size-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                            @else
                                <svg class="size-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                            @endif
                        </div>

                        <h3 class="text-xl font-bold text-slate-900">{{ $step['title'] }}</h3>
                        <p class="mt-3 text-sm text-slate-600 max-w-xs leading-relaxed">
                            {{ $step['description'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- 2.6 Final Call to Action -->
    <section id="cta" class="relative overflow-hidden bg-emerald-900 py-16 text-white sm:py-20">
        <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                Ready to take control of your team's project security?
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-base text-emerald-100 sm:text-lg">
                Join teams using Teamate to enforce zero-trust role-based access and decentralized backups.
            </p>
            <div class="mt-8 flex justify-center">
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center justify-center rounded-xl bg-white px-8 py-3.5 text-base font-bold text-emerald-900 shadow-lg hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white transition"
                >
                    Get started
                </a>
            </div>
        </div>
    </section>
@endsection
