<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct() {}

    /**
     * Display the authenticated user's teams.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $teams = $user->allTeams();

        return view('dashboard', compact('teams'));
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:teams,slug', 'regex:/^[a-z0-9-]+$/'],
        ], [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This team URL is already taken. Please choose another slug.',
        ]);

        $user = $request->user();

        $team = DB::transaction(function () use ($user, $validated) {
            // create new team
            $team = Team::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'owner_id' => $user->id,
            ]);

            // create admin role for team
            $teamAdminRoleCode = 'team_administrator_'.$team->slug;

            $role = Roles::create([
                'code' => $teamAdminRoleCode,
                'name' => 'Admin',
                'scope_type' => 'team',
                'team_id' => $team->id,
                'is_system' => false,
            ]);

            // Adding to team
            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => 'admin',
            ]);

            // Grant role for user
            Membership::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_id' => $team->id,
            ]);

            // Grant resource to user's role

            // Grant permission 7 for team resource
            $teamResourceType = ResourceTypes::firstOrCreate([
                'code' => 'team',
            ]);
            ResourceGrants::create([
                'role_id' => $role->id,
                'resource_type_id' => $teamResourceType->id,
                'resource_specific' => $team->id,
                'permission_mask' => 7,
                'granted_by' => $user->id,
            ]);

            // Grant permission 7 for team_member resource
            $teamMemberResourceType = ResourceTypes::firstOrCreate([
                'code' => 'team_member',
            ]);
            ResourceGrants::create([
                'role_id' => $role->id,
                'resource_type_id' => $teamMemberResourceType->id,
                'resource_specific' => $team->id,
                'permission_mask' => 7,
                'granted_by' => $user->id,
            ]);

            // Grant permission 7 for team_role resource
            $teamRoleResourceType = ResourceTypes::firstOrCreate([
                'code' => 'team_role',
            ]);
            ResourceGrants::create([
                'role_id' => $role->id,
                'resource_type_id' => $teamRoleResourceType->id,
                'resource_specific' => $team->id,
                'permission_mask' => 7,
                'granted_by' => $user->id,
            ]);

            return $team;
        });

        return redirect()
            ->route('teams.show', $team)
            ->with('status', "Team '{$team->name}' created successfully!");
    }
}
