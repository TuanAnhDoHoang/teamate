<x-layouts::app :title="__('Manage project members')">
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
            <span class="font-semibold text-slate-800 dark:text-neutral-200">{{ __('Members') }}</span>
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
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ __('Manage project members') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">
                    {{ __('Add team members to :project and manage their project roles.', ['project' => $project->name]) }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('projects.roles.index', [$team, $project]) }}"
                    class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                    wire:navigate
                >
                    {{ __('Manage roles') }}
                </a>
                <a
                    href="{{ route('projects.show', [$team, $project]) }}"
                    class="text-sm font-semibold text-slate-600 hover:text-slate-800 dark:text-neutral-400 dark:hover:text-neutral-200"
                    wire:navigate
                >
                    {{ __('Back to project') }}
                </a>
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
            <div class="mb-5">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Add a team member') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                    {{ __('Only people who are already members of this team can be added to the project.') }}
                </p>
            </div>

            @if ($availableTeamMembers->isNotEmpty() && $roles->isNotEmpty())
                <form method="POST" action="{{ route('projects.members.store', [$team, $project]) }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    @csrf

                    <div class="w-full max-w-md">
                        <label for="project-member-user" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                            {{ __('Team member') }}
                        </label>
                        <select
                            id="project-member-user"
                            name="user_id"
                            required
                            class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-700 shadow-xs dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                        >
                            <option value="">{{ __('Select a team member') }}</option>
                            @foreach ($availableTeamMembers as $teamMember)
                                <option value="{{ $teamMember->user_id }}" @selected((string) old('user_id') === (string) $teamMember->user_id)>
                                    {{ $teamMember->user->name }} ({{ $teamMember->user->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full max-w-xs">
                        <label for="project-member-role" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                            {{ __('Project role') }}
                        </label>
                        <select
                            id="project-member-role"
                            name="role_id"
                            required
                            class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-700 shadow-xs dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                        >
                            <option value="">{{ __('Select a role') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-emerald-700"
                    >
                        {{ __('Add to project') }}
                    </button>
                </form>
            @elseif ($roles->isEmpty())
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    {{ __('Create a project role before adding members.') }}
                </p>
            @else
                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-neutral-800 dark:text-neutral-300">
                    {{ __('All team members have already been added to this project.') }}
                </p>
            @endif
        </section>

        <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Project members') }}</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                        {{ trans_choice(':count member|:count members', $projectMembers->count(), ['count' => $projectMembers->count()]) }}
                    </p>
                </div>
            </div>

            @forelse ($projectMembers as $projectMember)
                @php
                    $membership = $memberships->get($projectMember->user_id);
                    $isCurrentUser = (int) $projectMember->user_id === (int) $currentUserId;
                @endphp
                <article class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ $projectMember->user->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">{{ $projectMember->user->email }}</p>
                        @if ($isCurrentUser)
                            <span class="mt-2 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                {{ __('You') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-col gap-3 sm:min-w-80 sm:flex-row sm:items-center">
                        @if ($isCurrentUser)
                            <span class="text-sm font-medium text-slate-600 dark:text-neutral-300">
                                {{ $membership?->role?->name ?? $projectMember->role }}
                            </span>
                        @else
                            <form method="POST" action="{{ route('projects.members.role.update', [$team, $project, $projectMember]) }}" class="flex flex-1 items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="member-role-{{ $projectMember->id }}" class="sr-only">{{ __('Project role') }}</label>
                                <select
                                    id="member-role-{{ $projectMember->id }}"
                                    name="role_id"
                                    required
                                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                >
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected((int) $membership?->role_id === (int) $role->id)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button
                                    type="submit"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                >
                                    {{ __('Save') }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('projects.members.destroy', [$team, $project, $projectMember]) }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="w-full rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40 sm:w-auto"
                                >
                                    {{ __('Remove') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500 dark:border-neutral-700 dark:text-neutral-400">
                    {{ __('No members have been added to this project yet.') }}
                </p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
