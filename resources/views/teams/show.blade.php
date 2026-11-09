<x-layouts::app :title="$team->name">
    <div
        x-data="{
            activeTab: 'projects',
            createProjectModalOpen: false,
            addMemberModalOpen: false,
            projectName: '',
            projectDescription: '',
            memberEmail: '',
        }"
        class="flex flex-col gap-8 w-full"
    >
        <!-- Breadcrumb / Back Navigation -->
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-neutral-400">
            <a href="{{ route('dashboard') }}" class="hover:text-emerald-600 transition" wire:navigate>
                {{ __('Teams') }}
            </a>
            <svg class="size-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-slate-800 dark:text-neutral-200 font-semibold">{{ $team->name }}</span>
        </nav>

        <!-- Status Alert -->
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                <svg class="size-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- 5.2 Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-6 dark:border-neutral-800">
            <div class="flex items-center gap-4">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-600 text-white text-2xl font-black shadow-md">
                    {{ strtoupper(substr($team->name, 0, 1)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                            {{ $team->name }}
                        </h1>
                        <!-- Current user's role badge -->
                        <x-role-badge :role="$userRole" />
                    </div>
                    <p class="mt-1 text-xs text-slate-400 font-mono">
                        teamate.app/teams/{{ $team->slug }}
                    </p>
                </div>
            </div>

            {{-- Action button: visible only to users with Own permission on the team resource. --}}
            @if ($canOwn)
                <div>
                    <button
                        type="button"
                        @click="createProjectModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Create project') }}
                    </button>
                </div>
            @endif
        </div>

        <!-- 5.2 Tabs: "Projects" (default) and "Members" -->
        <div class="flex items-center gap-6 border-b border-slate-200 dark:border-neutral-800 text-sm font-semibold">
            <button
                type="button"
                @click="activeTab = 'projects'"
                :class="activeTab === 'projects'
                    ? 'border-emerald-600 text-emerald-600 border-b-2 pb-3.5 -mb-px'
                    : 'text-slate-500 hover:text-slate-800 pb-3.5 dark:text-neutral-400 dark:hover:text-white'"
                class="transition"
            >
                {{ __('Projects') }}
                <span class="ml-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-neutral-800 dark:text-neutral-300">
                    {{ $projects->count() }}
                </span>
            </button>
            <button
                type="button"
                @click="activeTab = 'members'"
                :class="activeTab === 'members'
                    ? 'border-emerald-600 text-emerald-600 border-b-2 pb-3.5 -mb-px'
                    : 'text-slate-500 hover:text-slate-800 pb-3.5 dark:text-neutral-400 dark:hover:text-white'"
                class="transition"
            >
                {{ __('Members') }}
                <span class="ml-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-neutral-800 dark:text-neutral-300">
                    {{ $members->count() }}
                </span>
            </button>
        </div>

        <!-- Tab 1: Projects List -->
        <div x-show="activeTab === 'projects'">
            @if ($projects->isEmpty())
                <!-- 5.2 Empty State -->
                <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/50 px-6 py-20 text-center dark:border-neutral-800 dark:bg-neutral-900/40">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400 mb-4 shadow-2xs">
                        <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ __('No projects in this team yet') }}
                    </h2>
                    <p class="mt-1.5 max-w-sm text-sm text-slate-500 dark:text-neutral-400 leading-relaxed">
                        @if ($canOwn)
                            {{ __('Create your first project to start organizing repositories, files and directories.') }}
                        @else
                            {{ __('No projects have been assigned or created in this team yet.') }}
                        @endif
                    </p>
                    @if ($canOwn)
                        <div class="mt-6">
                            <button
                                type="button"
                                @click="createProjectModalOpen = true"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('Create project') }}
                            </button>
                        </div>
                    @endif
                </div>
            @else
                <!-- Project List Table / Cards -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600 dark:text-neutral-300">
                            <thead class="border-b border-slate-100 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-neutral-800 dark:bg-neutral-800/50">
                                <tr>
                                    <th class="px-6 py-4">{{ __('Project Name') }}</th>
                                    <th class="px-6 py-4">{{ __('Description') }}</th>
                                    <th class="px-6 py-4 text-center">{{ __('Status') }}</th>
                                    <th class="px-6 py-4">{{ __('Created Date') }}</th>
                                    <th class="px-6 py-4 text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-neutral-800">
                                @foreach ($projects as $project)
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-neutral-800/40 transition">
                                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                            <a
                                                href="{{ route('projects.show', [$team, $project]) }}"
                                                class="flex items-center gap-2.5 hover:text-emerald-600 transition"
                                                wire:navigate
                                            >
                                                <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 shrink-0">
                                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                    </svg>
                                                </div>
                                                <span class="font-bold text-sm">{{ $project->name }}</span>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-500 dark:text-neutral-400 max-w-xs truncate">
                                            {{ $project->description ?: '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if ($project->status === 'active')
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800">
                                                    Active
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200 dark:bg-neutral-800 dark:text-neutral-400">
                                                    Archived
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-500 dark:text-neutral-400">
                                            {{ $project->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a
                                                href="{{ route('projects.show', [$team, $project]) }}"
                                                class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 hover:text-emerald-700 transition"
                                                wire:navigate
                                            >
                                                {{ __('Open') }} &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Tab 2: Members (Placeholder list of members with role badges) -->
        <div x-show="activeTab === 'members'" x-cloak>
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between dark:border-neutral-800 dark:bg-neutral-800/40">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Team Members & Role Assignments') }}</h3>
                    <div class="flex items-center gap-2">
                        @if ($canManageRoles)
                            <a
                                href="{{ route('teams.roles.index', $team) }}"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-white dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-900"
                            >
                                {{ __('Manage roles') }}
                            </a>
                        @endif
                        @if ($canAddMember)
                            <button
                                type="button"
                                @click="addMemberModalOpen = true; activeTab = 'members'"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('Add member') }}
                            </button>
                        @endif
                    </div>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-neutral-800">
                    @foreach ($members as $membership)
                        <div class="flex items-center justify-between px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-700 text-sm dark:bg-neutral-800 dark:text-neutral-200">
                                    {{ $membership->user->initials() }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $membership->user->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-neutral-400">{{ $membership->user->email }}</p>
                                </div>
                            </div>
                            @if ($canAddMember && $membership->user_id !== auth()->id() && $assignableRoles->isNotEmpty())
                                <form
                                    method="POST"
                                    action="{{ route('teams.members.role.update', [$team, $membership]) }}"
                                    class="flex items-center gap-2"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="member-role-{{ $membership->id }}">
                                        {{ __('Role for :name', ['name' => $membership->user->name]) }}
                                    </label>
                                    <select
                                        id="member-role-{{ $membership->id }}"
                                        name="role_id"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        @foreach ($assignableRoles as $role)
                                            <option
                                                value="{{ $role->id }}"
                                                @selected(($memberRoleIds[$membership->user_id] ?? null) == $role->id)
                                            >
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button
                                        type="submit"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                    >
                                        {{ __('Update') }}
                                    </button>
                                </form>
                            @else
                                <x-role-badge :role="$memberRoleNames[$membership->user_id] ?? $membership->role" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($canAddMember)
            <div
                x-cloak
                x-show="addMemberModalOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-member-title"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs"
                    @click="addMemberModalOpen = false"
                ></div>

                <div class="flex min-h-screen items-center justify-center p-4">
                    <div
                        class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:border dark:border-neutral-800 dark:bg-neutral-900 sm:p-8"
                        @click.stop
                    >
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-neutral-800">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white" id="modal-member-title">
                                {{ __('Add team member') }}
                            </h2>
                            <button
                                type="button"
                                @click="addMemberModalOpen = false"
                                class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                            >
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('teams.members.store', $team) }}" class="mt-6 flex flex-col gap-5">
                            @csrf

                            <div>
                                <label for="member_email" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                    {{ __('Email address') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="member_email"
                                    name="email"
                                    type="email"
                                    x-model="memberEmail"
                                    required
                                    autofocus
                                    placeholder="member@example.com"
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                                />
                                @error('email')
                                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-neutral-800 dark:text-neutral-400">
                                {{ __('New members are assigned the Guest role.') }}
                            </p>

                            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4 dark:border-neutral-800">
                                <button
                                    type="button"
                                    @click="addMemberModalOpen = false"
                                    class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700"
                                >
                                    {{ __('Add member') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- 5.2 Modal Dialog for Project Creation --}}
        @if ($canOwn)
            <div
                x-cloak
                x-show="createProjectModalOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-project-title"
                role="dialog"
                aria-modal="true"
            >
                <div
                    x-show="createProjectModalOpen"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"
                    @click="createProjectModalOpen = false"
                ></div>

                <div class="flex min-h-screen items-center justify-center p-4">
                    <div
                        x-show="createProjectModalOpen"
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
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                </span>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white" id="modal-project-title">
                                    {{ __('Create a new project') }}
                                </h2>
                            </div>
                            <button
                                type="button"
                                @click="createProjectModalOpen = false"
                                class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                            >
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('teams.projects.store', $team) }}" class="mt-6 flex flex-col gap-5">
                            @csrf

                            <!-- Name (Required) -->
                            <div>
                                <label for="proj_name" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                    {{ __('Project Name') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="proj_name"
                                    name="name"
                                    type="text"
                                    x-model="projectName"
                                    required
                                    autofocus
                                    placeholder="Security Core Framework"
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                                />
                                @error('name')
                                    <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Description (Optional) -->
                            <div>
                                <label for="proj_desc" class="block text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                    {{ __('Description (Optional)') }}
                                </label>
                                <textarea
                                    id="proj_desc"
                                    name="description"
                                    rows="3"
                                    x-model="projectDescription"
                                    placeholder="Brief summary of repository goals and deliverables..."
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                                ></textarea>
                                @error('description')
                                    <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mt-4 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-neutral-800">
                                <button
                                    type="button"
                                    @click="createProjectModalOpen = false"
                                    class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >
                                    {{ __('Create project') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
