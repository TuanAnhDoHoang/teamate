<x-layouts::app :title="__('Manage team roles')">
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
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ __('Manage team roles') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">
                    {{ __('Create roles and configure resource permissions for :team.', ['team' => $team->name]) }}
                </p>
            </div>
            <a
                href="{{ route('teams.show', $team) }}"
                class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                wire:navigate
            >
                {{ __('Back to team') }}
            </a>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
            <div class="mb-5">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Add a role') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                    {{ __('You can only grant permissions for resources you own.') }}
                </p>
            </div>

            <form method="POST" action="{{ route('teams.roles.store', $team) }}" class="flex flex-col gap-5">
                @csrf

                <div class="max-w-xl">
                    <label for="new-role-name" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                        {{ __('Role name') }}
                    </label>
                    <input
                        id="new-role-name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        maxlength="255"
                        class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                    />
                </div>

                @if ($manageableResourceTypes->isNotEmpty())
                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-neutral-800">
                        <table class="w-full min-w-[36rem] text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-neutral-800/60 dark:text-neutral-400">
                                <tr>
                                    <th class="px-4 py-3">{{ __('Resource') }}</th>
                                    <th class="px-4 py-3">{{ __('Permission') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-neutral-800">
                                @foreach ($manageableResourceTypes as $resourceType)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-neutral-200">
                                            {{ str($resourceType->code)->replace('_', ' ')->title() }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <select
                                                name="permissions[{{ $resourceType->code }}]"
                                                class="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                            >
                                                @foreach ($permissionOptions as $mask => $label)
                                                    <option value="{{ $mask }}" @selected((int) old('permissions.'.$resourceType->code, 0) === $mask)>
                                                        {{ __($label) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-neutral-800 dark:text-neutral-300">
                        {{ __('You do not have Own permission on any resources, so this role will have no resource permissions.') }}
                    </p>
                @endif

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
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Roles in this team') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                    {{ __('System roles are available for assignment but cannot be edited here.') }}
                </p>
            </div>

            @forelse ($roles as $role)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white">{{ $role->name }}</h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                                {{ trans_choice(':count member|:count members', $role->memberships_count, ['count' => $role->memberships_count]) }}
                            </p>
                        </div>
                        @if ($role->is_system)
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ __('System role') }}
                            </span>
                        @endif
                    </div>

                    @if ($role->team_id === $team->id && ! $role->is_system)
                        <form method="POST" action="{{ route('teams.roles.update', [$team, $role]) }}" class="flex flex-col gap-5">
                            @csrf
                            @method('PUT')

                            <div class="max-w-xl">
                                <label for="role-name-{{ $role->id }}" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                    {{ __('Role name') }}
                                </label>
                                <input
                                    id="role-name-{{ $role->id }}"
                                    name="name"
                                    type="text"
                                    value="{{ $role->name }}"
                                    required
                                    maxlength="255"
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                                />
                            </div>

                            @if ($manageableResourceTypes->isNotEmpty())
                                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-neutral-800">
                                    <table class="w-full min-w-[36rem] text-left text-sm">
                                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-neutral-800/60 dark:text-neutral-400">
                                            <tr>
                                                <th class="px-4 py-3">{{ __('Resource') }}</th>
                                                <th class="px-4 py-3">{{ __('Permission') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-neutral-800">
                                            @foreach ($manageableResourceTypes as $resourceType)
                                                @php
                                                    $currentGrant = $role->resourceGrants->first(
                                                        fn ($grant) => $grant->resourceType?->code === $resourceType->code
                                                    );
                                                @endphp
                                                <tr>
                                                    <td class="px-4 py-3 font-medium text-slate-800 dark:text-neutral-200">
                                                        {{ str($resourceType->code)->replace('_', ' ')->title() }}
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <select
                                                            name="permissions[{{ $resourceType->code }}]"
                                                            class="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                                        >
                                                            @foreach ($permissionOptions as $mask => $label)
                                                                <option
                                                                    value="{{ $mask }}"
                                                                    @selected((int) ($currentGrant?->permission_mask ?? 0) === $mask)
                                                                >
                                                                    {{ __($label) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div>
                                <button
                                    type="submit"
                                    class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                >
                                    {{ __('Save changes') }}
                                </button>
                            </div>
                        </form>
                    @else
                        @if ($role->resourceGrants->isNotEmpty())
                            <ul class="flex flex-wrap gap-2">
                                @foreach ($role->resourceGrants as $grant)
                                    <li class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ str($grant->resourceType?->code ?? 'resource')->replace('_', ' ')->title() }}:
                                        {{ collect([
                                            1 => 'Own',
                                            2 => 'Write',
                                            4 => 'Read',
                                        ])->filter(fn ($label, $bit) => ($grant->permission_mask & $bit) === $bit)->implode(', ') }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500 dark:text-neutral-400">{{ __('No resource permissions configured.') }}</p>
                        @endif
                    @endif
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500 dark:border-neutral-700 dark:text-neutral-400">
                    {{ __('No roles have been created for this team yet.') }}
                </p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
