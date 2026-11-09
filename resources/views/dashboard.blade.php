<x-layouts::app :title="__('Your teams')">
    <div
        x-data="{
            createModalOpen: false,
            name: '{{ old('name', '') }}',
            slug: '{{ old('slug', '') }}',
            manuallyEditedSlug: false,
            generateSlug() {
                if (!this.manuallyEditedSlug) {
                    this.slug = this.name
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                }
            }
        }"
        class="flex flex-col gap-8 w-full"
    >
        <!-- Flash Alert -->
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                <svg class="size-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Your teams') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">
                    {{ __('Manage your team workspaces and project repositories.') }}
                </p>
            </div>
            <div>
                <button
                    type="button"
                    @click="createModalOpen = true"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Create team') }}
                </button>
            </div>
        </div>

        @if ($teams->isEmpty())
            <!-- 5.1 Empty State -->
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/50 px-6 py-20 text-center dark:border-neutral-800 dark:bg-neutral-900/40">
                <div class="flex size-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400 mb-4 shadow-2xs">
                    <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="4" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                    {{ __("You don't have any teams yet") }}
                </h2>
                <p class="mt-1.5 max-w-sm text-sm text-slate-500 dark:text-neutral-400 leading-relaxed">
                    {{ __('Create a team to start organizing projects and inviting members.') }}
                </p>
                <div class="mt-6">
                    <button
                        type="button"
                        @click="createModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Create team') }}
                    </button>
                </div>
            </div>
        @else
            <!-- 5.1 Grid of Team Cards (3 columns desktop, 1 mobile) -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($teams as $team)
                    @php
                        $userRole = $team->roleFor(auth()->user());
                        $initial = strtoupper(substr($team->name, 0, 1));
                    @endphp
                    <a
                        href="{{ route('teams.show', $team) }}"
                        class="group relative flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs hover:border-emerald-300 hover:shadow-md transition-all duration-150 dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-700"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <!-- Initial letter avatar -->
                                <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-100 font-extrabold text-emerald-800 text-lg shadow-2xs dark:bg-emerald-950/60 dark:text-emerald-300">
                                    {{ $initial }}
                                </div>
                                <!-- User's role badge -->
                                <x-role-badge :role="$userRole" />
                            </div>

                            <h3 class="text-lg font-bold text-slate-900 group-hover:text-emerald-600 transition dark:text-white dark:group-hover:text-emerald-400 truncate">
                                {{ $team->name }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-400 font-mono">
                                teamate.app/teams/{{ $team->slug }}
                            </p>
                        </div>

                        <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4 text-xs font-medium text-slate-500 dark:border-neutral-800 dark:text-neutral-400">
                            <span class="flex items-center gap-1.5">
                                <svg class="size-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                {{ $team->projects_count ?? $team->projects()->count() }} {{ Str::plural('project', $team->projects_count ?? $team->projects()->count()) }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold group-hover:translate-x-0.5 transition-transform">
                                {{ __('Open') }} &rarr;
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <!-- 5.1 Create Team Modal (Alpine.js) -->
        <div
            x-cloak
            x-show="createModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div
                x-show="createModalOpen"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"
                @click="createModalOpen = false"
            ></div>

            <div class="flex min-h-screen items-center justify-center p-4">
                <div
                    x-show="createModalOpen"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                    class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-neutral-900 dark:border dark:border-neutral-800 sm:p-8"
                    @click.stop
                >
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-neutral-800">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <rect width="18" height="18" x="3" y="3" rx="4" />
                                    <path d="m9 12 2 2 4-4" />
                                </svg>
                            </span>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white" id="modal-title">
                                {{ __('Create a new team') }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            @click="createModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                        >
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('teams.store') }}" class="mt-6 flex flex-col gap-5">
                        @csrf

                        <!-- Team Name -->
                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                {{ __('Team Name') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                x-model="name"
                                @input="generateSlug()"
                                required
                                autofocus
                                placeholder="Acme Engineering"
                                class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                            />
                            @error('name')
                                <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Team Slug -->
                        <div>
                            <label for="slug" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                {{ __('Team URL Slug') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="mt-1.5 flex rounded-xl border border-slate-300 shadow-xs focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800">
                                <span class="inline-flex items-center px-3 text-xs text-slate-400 border-r border-slate-200 dark:border-neutral-700 select-none">
                                    teamate.app/teams/
                                </span>
                                <input
                                    id="slug"
                                    name="slug"
                                    type="text"
                                    x-model="slug"
                                    @input="manuallyEditedSlug = true"
                                    required
                                    placeholder="acme-engineering"
                                    class="block w-full border-0 px-3 py-2.5 text-sm focus:outline-hidden dark:bg-neutral-800 dark:text-white"
                                />
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-neutral-400">
                                {{ __('Preview: ') }}<span class="font-mono text-emerald-600 font-medium" x-text="'teamate.app/teams/' + (slug || '{slug}')"></span>
                            </p>
                            @error('slug')
                                <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="createModalOpen = false"
                                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            >
                                {{ __('Create team') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
