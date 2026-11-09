<?php

use App\Models\Membership;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

/**
 * @return array{team: Team, user: User, role: Roles, resourceTypes: array<string, ResourceTypes>}
 */
function createTeamRoleManager(array $permissionMasks = []): array
{
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Role Management Team',
        'slug' => 'role-management-team-'.$user->id,
        'owner_id' => $user->id,
    ]);

    $role = Roles::create([
        'code' => 'team_administrator_'.$team->slug,
        'name' => 'Admin',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);

    TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'role' => $role->name,
    ]);

    Membership::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_id' => $team->id,
    ]);

    $resourceTypes = [];

    foreach (['team', 'team_member', 'team_role', 'project'] as $resourceTypeCode) {
        $resourceType = ResourceTypes::firstOrCreate(['code' => $resourceTypeCode]);
        $resourceTypes[$resourceTypeCode] = $resourceType;
        $permissionMask = $permissionMasks[$resourceTypeCode]
            ?? match ($resourceTypeCode) {
                'project' => ResourceGrants::OWN,
                default => 7,
            };

        if ($permissionMask > 0) {
            ResourceGrants::create([
                'role_id' => $role->id,
                'resource_type_id' => $resourceType->id,
                'resource_specific' => $team->id,
                'permission_mask' => $permissionMask,
                'granted_by' => $user->id,
            ]);
        }
    }

    return compact('team', 'user', 'role', 'resourceTypes');
}

test('team role writer can create a role with permissions on resources they own', function () {
    ['team' => $team, 'user' => $user, 'resourceTypes' => $resourceTypes] = createTeamRoleManager();

    $response = $this->actingAs($user)->post(route('teams.roles.store', $team), [
        'name' => 'Project Viewer',
        'permissions' => [
            'project' => ResourceGrants::READ,
            'team' => 0,
            'team_member' => 0,
            'team_role' => 0,
        ],
    ]);

    $response->assertRedirect(route('teams.roles.index', $team));
    $role = Roles::query()->where('team_id', $team->id)->where('name', 'Project Viewer')->firstOrFail();
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $role->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $team->id,
        'permission_mask' => ResourceGrants::READ,
        'granted_by' => $user->id,
    ]);
});

test('team role writer can view role configuration and owned resources', function () {
    ['team' => $team, 'user' => $user] = createTeamRoleManager();

    $response = $this->actingAs($user)->get(route('teams.roles.index', $team));

    $response->assertOk();
    $response->assertSee('Add a role');
    $response->assertSee('Project');
    $response->assertSee('Roles in this team');
});

test('team role writer cannot grant permission on a resource they do not own', function () {
    ['team' => $team, 'user' => $user] = createTeamRoleManager([
        'project' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('teams.roles.store', $team), [
        'name' => 'Unauthorized Project Role',
        'permissions' => ['project' => ResourceGrants::READ],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('roles', [
        'team_id' => $team->id,
        'name' => 'Unauthorized Project Role',
    ]);
});

test('user without team role write permission cannot create a team role', function () {
    ['team' => $team, 'user' => $user] = createTeamRoleManager([
        'team_role' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('teams.roles.store', $team), [
        'name' => 'Unauthorized Role',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('roles', [
        'team_id' => $team->id,
        'name' => 'Unauthorized Role',
    ]);
});

test('team role writer can update permissions on resources they own', function () {
    ['team' => $team, 'user' => $user, 'resourceTypes' => $resourceTypes] = createTeamRoleManager();
    $role = Roles::create([
        'code' => 'team_'.$team->id.'_temporary',
        'name' => 'Temporary',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    ResourceGrants::create([
        'role_id' => $role->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $team->id,
        'permission_mask' => ResourceGrants::READ,
        'granted_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('teams.roles.update', [$team, $role]), [
        'name' => 'Project Contributor',
        'permissions' => [
            'project' => ResourceGrants::WRITE,
            'team' => 0,
            'team_member' => 0,
            'team_role' => 0,
        ],
    ]);

    $response->assertRedirect(route('teams.roles.index', $team));
    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'Project Contributor',
    ]);
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $role->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $team->id,
        'permission_mask' => ResourceGrants::WRITE,
        'granted_by' => $user->id,
    ]);
});

test('team member writer can assign a role without checking the role resource permissions', function () {
    ['team' => $team, 'user' => $user, 'resourceTypes' => $resourceTypes] = createTeamRoleManager([
        'project' => 0,
    ]);
    $memberUser = User::factory()->create();
    $member = TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $memberUser->id,
        'role' => 'Guest',
    ]);
    $guestRole = Roles::create([
        'code' => 'guest',
        'name' => 'Guest',
        'scope_type' => 'team',
        'is_system' => true,
    ]);
    Membership::create([
        'user_id' => $memberUser->id,
        'role_id' => $guestRole->id,
        'scope_id' => $team->id,
    ]);
    $viewerRole = Roles::create([
        'code' => 'team_'.$team->id.'_viewer',
        'name' => 'Viewer',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    ResourceGrants::create([
        'role_id' => $viewerRole->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $team->id,
        'permission_mask' => ResourceGrants::OWN,
        'granted_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->patch(route('teams.members.role.update', [$team, $member]), [
        'role_id' => $viewerRole->id,
    ]);

    $response->assertRedirect(route('teams.show', $team));
    $this->assertDatabaseHas('memberships', [
        'user_id' => $memberUser->id,
        'scope_id' => $team->id,
        'role_id' => $viewerRole->id,
    ]);
    $this->assertDatabaseHas('team_members', [
        'id' => $member->id,
        'role' => 'Viewer',
    ]);
});

test('team member writer cannot change their own role', function () {
    ['team' => $team, 'user' => $user, 'role' => $currentRole] = createTeamRoleManager();
    $newRole = Roles::create([
        'code' => 'team_'.$team->id.'_new-role',
        'name' => 'New Role',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    $selfMembership = TeamMember::query()
        ->where('team_id', $team->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    $response = $this->actingAs($user)->patch(route('teams.members.role.update', [$team, $selfMembership]), [
        'role_id' => $newRole->id,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('memberships', [
        'user_id' => $user->id,
        'scope_id' => $team->id,
        'role_id' => $currentRole->id,
    ]);
});
