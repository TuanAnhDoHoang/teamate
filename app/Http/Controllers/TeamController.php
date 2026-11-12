<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Display the specified team and its projects/members.
     */
    public function show(Request $request, Team $team): View
    {
        $user = $request->user();

        if (! $team->canUserRead($user)) {
            abort(403, 'You do not have permission to access this team.');
        }

        $userRole = $team->roleFor($user);
        $canOwn = $team->canUserOwn($user);
        $canAddMember = $team->canUserWriteTeamMembers($user);
        $canManageRoles = $team->canUserWriteTeamRoles($user);

        // Fetch projects: exclude soft-deleted projects
        $projects = $team->projects()
            ->latest()
            ->get();

        // Load members for the Members tab
        $members = $team->teamMembers()
            ->with('user')
            ->get();

        $assignableRoles = collect();
        $memberRoleIds = collect();
        $memberRoleNames = collect();

        if ($canAddMember) {
            $assignableRoles = Roles::query()
                ->where('scope_type', 'team')
                ->where(function ($query) use ($team) {
                    $query->where('team_id', $team->id)
                        ->orWhere(function ($query) {
                            $query->whereNull('team_id')
                                ->where('code', 'guest')
                                ->where('is_system', true);
                        });
                })
                ->orderBy('name')
                ->get()
                ->filter(fn (Roles $role): bool => $team->canUserAssignRole($user, $role))
                ->values();

            $memberMemberships = Membership::query()
                ->with('role')
                ->where('scope_id', $team->id)
                ->where('scope_type', 'team')
                ->get()
                ->keyBy('user_id');

            $memberRoleIds = $memberMemberships->map(fn (Membership $membership): int => (int) $membership->role_id);
            $memberRoleNames = $memberMemberships->map(fn (Membership $membership): ?string => $membership->role?->name);
        }

        return view('teams.show', compact(
            'team',
            'projects',
            'members',
            'userRole',
            'canOwn',
            'canAddMember',
            'canManageRoles',
            'assignableRoles',
            'memberRoleIds',
            'memberRoleNames',
        ));
    }

    /**
     * Store a newly created project in the team.
     */
    public function storeProject(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (! $team->canUserEdit($user)) {
            abort(403, 'You do not have Write permission to create projects in this team.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Auto-generate slug unique within this team
        $baseSlug = Str::slug($validated['name']);
        if ($baseSlug === '') {
            $baseSlug = 'project';
        }

        $slug = $baseSlug;
        $counter = 1;
        while ($team->projects()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $teamMembership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $teamRole = $teamMembership->role()->firstOrFail();

        $project = DB::transaction(function () use ($team, $user, $teamRole, $validated, $slug): Project {
            $project = $team->projects()->create([
                'owner_id' => $user->id,
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'status' => 'active',
            ]);

            $projectRole = Roles::create([
                'code' => 'project_'.$project->id.'_member',
                'name' => $teamRole->name,
                'scope_type' => 'project',
                'team_id' => $team->id,
                'is_system' => false,
            ]);

            $projectResourceType = ResourceTypes::firstOrCreate([
                'code' => 'project',
            ]);
            ResourceGrants::create([
                'role_id' => $projectRole->id,
                'resource_type_id' => $projectResourceType->id,
                'resource_specific' => $project->id,
                'permission_mask' => 7,
                'granted_by' => $user->id,
            ]);

            Membership::create([
                'user_id' => $user->id,
                'role_id' => $projectRole->id,
                'scope_id' => $project->id,
                'scope_type' => 'project',
            ]);

            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => $teamRole->name,
            ]);

            return $project;
        });

        return redirect()
            ->route('projects.show', [$team, $project])
            ->with('status', "Project '{$project->name}' created successfully!");
    }

    public function storeMember(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (! $team->canUserWriteTeamMembers($user)) {
            abort(403, 'You do not have permission to add members to this team.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $member = User::where('email', $validated['email'])->firstOrFail();

        if ($team->teamMembers()->where('user_id', $member->id)->exists()) {
            return back()->withErrors([
                'email' => 'This user is already a member of the team.',
            ]);
        }

        DB::transaction(function () use ($team, $member) {
            $guestRole = Roles::firstOrCreate(
                [
                    'code' => 'guest',
                    'scope_type' => 'team',
                ],
                [
                    'name' => 'Guest',
                    'is_system' => true,
                ],
            );

            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $member->id,
                'role' => 'guest',
            ]);

            Membership::updateOrCreate(
                [
                    'user_id' => $member->id,
                    'scope_id' => $team->id,
                    'scope_type' => 'team',
                ],
                [
                    'role_id' => $guestRole->id,
                ],
            );
        });

        return redirect()
            ->route('teams.show', $team)
            ->with('status', "{$member->name} was added to the team.");
    }
}
