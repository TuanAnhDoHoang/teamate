<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Roles;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectMemberController extends Controller
{
    public function index(Request $request, Team $team, Project $project): View
    {
        $user = $request->user();
        $this->authorizeProjectMemberManagement($team, $project, $user);

        $projectMembers = $project->projectMembers()
            ->with('user')
            ->orderBy('id')
            ->get();

        $memberships = $project->memberships()
            ->with('role')
            ->get()
            ->keyBy('user_id');

        $availableTeamMembers = $team->teamMembers()
            ->with('user')
            ->whereNotIn('user_id', $project->projectMembers()->select('user_id'))
            ->orderBy('id')
            ->get();

        $roles = $this->availableRoles($project, $team);
        $currentUserId = $user->id;

        return view('projects.members', compact(
            'team',
            'project',
            'projectMembers',
            'memberships',
            'availableTeamMembers',
            'roles',
            'currentUserId',
        ));
    }

    public function store(Request $request, Team $team, Project $project): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeProjectMemberManagement($team, $project, $user);

        $roles = $this->availableRoles($project, $team);
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('team_members', 'user_id')
                    ->where(fn (QueryBuilder $query) => $query->where('team_id', $team->id)),
            ],
            'role_id' => ['required', 'integer', Rule::in($roles->pluck('id')->all())],
        ]);

        if ($project->projectMembers()->where('user_id', $validated['user_id'])->exists()) {
            return back()
                ->withErrors(['user_id' => __('This person is already a member of the project.')])
                ->withInput();
        }

        $memberUser = User::query()->whereKey($validated['user_id'])->firstOrFail();
        $role = $roles->firstWhere('id', (int) $validated['role_id']);

        abort_if(! $role, 403);

        DB::transaction(function () use ($project, $memberUser, $role): void {
            $project->projectMembers()->create([
                'user_id' => $memberUser->id,
                'role' => $role->name,
            ]);

            Membership::updateOrCreate(
                [
                    'user_id' => $memberUser->id,
                    'scope_id' => $project->id,
                    'scope_type' => 'project',
                ],
                ['role_id' => $role->id],
            );
        });

        return redirect()
            ->route('projects.members.index', [$team, $project])
            ->with('status', __(':name was added to the project.', ['name' => $memberUser->name]));
    }

    public function updateRole(Request $request, Team $team, Project $project, ProjectMember $member): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeProjectMemberManagement($team, $project, $user);
        $this->ensureProjectMember($team, $project, $member);
        abort_if((int) $member->user_id === (int) $user->id, 403, __('You cannot change your own project role.'));

        $roles = $this->availableRoles($project, $team);
        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::in($roles->pluck('id')->all())],
        ]);
        $role = $roles->firstWhere('id', (int) $validated['role_id']);

        abort_if(! $role, 403);

        DB::transaction(function () use ($project, $member, $role): void {
            $project->memberships()
                ->where('user_id', $member->user_id)
                ->firstOrFail()
                ->update(['role_id' => $role->id]);

            $member->update(['role' => $role->name]);
        });

        return redirect()
            ->route('projects.members.index', [$team, $project])
            ->with('status', __('Project role updated.'));
    }

    public function destroy(Request $request, Team $team, Project $project, ProjectMember $member): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeProjectMemberManagement($team, $project, $user);
        $this->ensureProjectMember($team, $project, $member);
        abort_if((int) $member->user_id === (int) $user->id, 403, __('You cannot remove yourself from the project.'));

        DB::transaction(function () use ($project, $member): void {
            $project->memberships()
                ->where('user_id', $member->user_id)
                ->delete();

            $member->delete();
        });

        return redirect()
            ->route('projects.members.index', [$team, $project])
            ->with('status', __('Member removed from the project.'));
    }

    /**
     * @return Collection<int, Roles>
     */
    private function availableRoles(Project $project, Team $team): Collection
    {
        $rolePrefix = 'project_'.$project->id.'_';

        return Roles::query()
            ->where('scope_type', 'project')
            ->where(function (Builder $query) use ($team): void {
                $query->where('team_id', $team->id)
                    ->orWhere(function (Builder $query): void {
                        $query->whereNull('team_id')
                            ->where('code', 'guest')
                            ->where('is_system', true);
                    });
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (Roles $role): bool => str_starts_with($role->code, $rolePrefix)
                || ($role->code === 'guest' && $role->team_id === null && $role->is_system))
            ->values();
    }

    private function authorizeProjectMemberManagement(Team $team, Project $project, User $user): void
    {
        abort_unless((int) $project->team_id === (int) $team->id, 404);
        abort_unless($project->canUserOwn($user), 403);
    }

    private function ensureProjectMember(Team $team, Project $project, ProjectMember $member): void
    {
        abort_unless(
            (int) $member->project_id === (int) $project->id
                && $team->teamMembers()->where('user_id', $member->user_id)->exists(),
            404,
        );
    }
}
