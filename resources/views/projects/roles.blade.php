<x-layouts::app :title="__('Manage project roles')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-neutral-400">
            <a href="{{ route('dashboard') }}" class="transition hover:text-emerald-600" wire:navigate>
                {{ __('Teams') }}
            </a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('teams.show', $team) }}" class="transition hover:text-emerald-600" wire:navigate>
                {{ $team->name }}
            </a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('projects.show', [$team, $project]) }}" class="transition hover:text-emerald-600" wire:navigate>
                {{ $project->name }}
            </a>
            <span aria-hidden="true">/</span>
            <span class="font-semibold text-slate-800 dark:text-neutral-200">{{ __('Roles') }}</span>
        </nav>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <header class="flex flex-col gap-4 border-b border-slate-200 pb-6 dark:border-neutral-800 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ __('Manage project roles') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">
                    {{ __('Create roles and configure resource permissions for :project.', ['project' => $project->name]) }}
                </p>
            </div>
            <a
                href="{{ route('projects.show', [$team, $project]) }}"
                class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                wire:navigate
            >
                {{ __('Back to project') }}
            </a>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
            <div class="mb-5">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Add a role') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                    {{ __('You can configure the project and resources you own, including items inside folders you own.') }}
                </p>
            </div>

            <form method="POST" action="{{ route('projects.roles.store', [$team, $project]) }}" class="flex flex-col gap-5">
                @csrf

                <div class="max-w-xl">
                    <label for="new-project-role-name" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                        {{ __('Role name') }}
                    </label>
                    <input
                        id="new-project-role-name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        maxlength="255"
                        class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                    />
                </div>

                @include('projects.roles.partials.resource-permissions', [
                    'resourceTree' => $resourceTree,
                    'permissionOptions' => $permissionOptions,
                    'permissionValues' => [],
                    'formPrefix' => 'new-project-role',
                    'isNewRole' => true,
                ])

                <div>
                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-emerald-700"
                    >
                        {{ __('Create role') }}
                    </button>
                </div>
            </form>
        </section>

        <section class="flex flex-col gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Roles in this project') }}</h2>
            </div>

            @forelse ($roles as $role)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
                    <div class="mb-5">
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ $role->name }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                            {{ trans_choice(':count member|:count members', $role->memberships_count, ['count' => $role->memberships_count]) }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('projects.roles.update', [$team, $project, $role]) }}" class="flex flex-col gap-5">
                        @csrf
                        @method('PUT')

                        @php
                            $permissionValues = $role->resourceGrants->mapWithKeys(
                                fn ($grant) => [
                                    $grant->resourceType->code.':'.$grant->resource_specific => (int) $grant->permission_mask,
                                ]
                            )->all();
                        @endphp

                        <div class="max-w-xl">
                            <label for="project-role-name-{{ $role->id }}" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                {{ __('Role name') }}
                            </label>
                            <input
                                id="project-role-name-{{ $role->id }}"
                                name="name"
                                type="text"
                                value="{{ $role->name }}"
                                required
                                maxlength="255"
                                class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                            />
                        </div>

                        @include('projects.roles.partials.resource-permissions', [
                            'resourceTree' => $resourceTree,
                            'permissionOptions' => $permissionOptions,
                            'permissionValues' => $permissionValues,
                            'formPrefix' => 'project-role-'.$role->id,
                            'isNewRole' => false,
                        ])

                        <div>
                            <button
                                type="submit"
                                class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                            >
                                {{ __('Save changes') }}
                            </button>
                        </div>
                    </form>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500 dark:border-neutral-700 dark:text-neutral-400">
                    {{ __('No roles have been created for this project yet.') }}
                </p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
