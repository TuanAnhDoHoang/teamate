<?php

use App\Models\Membership;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

/**
 * @return array{team: Team, project: Project, user: User, role: Roles}
 */
function createProjectMemberManagementFixture(int $permissionMask = ResourceGrants::OWN): array
{
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Member Management Team',
        'slug' => 'member-management-team-'.$user->id,
        'owner_id' => $user->id,
    ]);
    TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'role' => 'Administrator',
    ]);

    $project = $team->projects()->create([
        'owner_id' => $user->id,
        'name' => 'Member Management Project',
        'slug' => 'member-management-project-'.$user->id,
        'status' => 'active',
    ]);
    $role = Roles::create([
        'code' => 'project_'.$project->id.'_administrator',
        'name' => 'Project Administrator',
        'scope_type' => 'project',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_id' => $project->id,
        'scope_type' => 'project',
    ]);
    ProjectMember::create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => $role->name,
    ]);

    $projectResourceType = ResourceTypes::firstOrCreate(['code' => 'project']);
    ResourceGrants::create([
        'role_id' => $role->id,
        'resource_type_id' => $projectResourceType->id,
        'resource_specific' => $project->id,
        'permission_mask' => $permissionMask,
        'granted_by' => $user->id,
    ]);

    return compact('team', 'project', 'user', 'role');
}

function createProjectMemberManagementRole(Project $project, Team $team, string $suffix, string $name): Roles
{
    return Roles::create([
        'code' => 'project_'.$project->id.'_'.$suffix,
        'name' => $name,
        'scope_type' => 'project',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
}

function addProjectMemberManagementTeamMember(Team $team, string $role = 'Guest'): User
{
    $user = User::factory()->create();
    TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}

test('project owner can view project members and available team members', function () {
    ['team' => $team, 'project' => $project, 'user' => $user] = createProjectMemberManagementFixture();
    $availableMember = addProjectMemberManagementTeamMember($team);

    $response = $this->actingAs($user)->get(route('projects.members.index', [$team, $project]));

    $response->assertOk();
    $response->assertSee($user->email);
    $response->assertSee($availableMember->email);
    $response->assertSee('Add to project');
});

test('project member management requires authentication', function () {
    ['team' => $team, 'project' => $project] = createProjectMemberManagementFixture();

    $response = $this->get(route('projects.members.index', [$team, $project]));

    $response->assertRedirect(route('login'));
});

test('project owner can add a team member with a project role', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectMemberManagementFixture();
    $newMember = addProjectMemberManagementTeamMember($team);

    $response = $this->actingAs($user)->post(route('projects.members.store', [$team, $project]), [
        'user_id' => $newMember->id,
        'role_id' => $role->id,
    ]);

    $response->assertRedirect(route('projects.members.index', [$team, $project]));
    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
        'role' => $role->name,
    ]);
    $this->assertDatabaseHas('memberships', [
        'user_id' => $newMember->id,
        'role_id' => $role->id,
        'scope_id' => $project->id,
        'scope_type' => 'project',
    ]);
});

test('project owner cannot assign a role from another project', function () {
    ['team' => $team, 'project' => $project, 'user' => $user] = createProjectMemberManagementFixture();
    $otherTeam = Team::create([
        'name' => 'Other Role Team',
        'slug' => 'other-role-team-'.$user->id,
        'owner_id' => $user->id,
    ]);
    $otherProject = $otherTeam->projects()->create([
        'owner_id' => $user->id,
        'name' => 'Other Role Project',
        'slug' => 'other-role-project-'.$user->id,
        'status' => 'active',
    ]);
    $otherRole = createProjectMemberManagementRole($otherProject, $otherTeam, 'reviewer', 'Other Reviewer');
    $newMember = addProjectMemberManagementTeamMember($team);

    $response = $this->actingAs($user)->post(route('projects.members.store', [$team, $project]), [
        'user_id' => $newMember->id,
        'role_id' => $otherRole->id,
    ]);

    $response->assertSessionHasErrors(['role_id']);
    $this->assertDatabaseMissing('project_members', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
    ]);
});

test('project owner cannot add someone who is not a team member', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectMemberManagementFixture();
    $nonTeamMember = User::factory()->create();

    $response = $this->actingAs($user)->post(route('projects.members.store', [$team, $project]), [
        'user_id' => $nonTeamMember->id,
        'role_id' => $role->id,
    ]);

    $response->assertSessionHasErrors(['user_id']);
    $this->assertDatabaseMissing('project_members', [
        'project_id' => $project->id,
        'user_id' => $nonTeamMember->id,
    ]);
});

test('project owner can change a project member role', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $currentRole] = createProjectMemberManagementFixture();
    $memberUser = addProjectMemberManagementTeamMember($team);
    $newRole = createProjectMemberManagementRole($project, $team, 'reviewer', 'Reviewer');
    $projectMember = $project->projectMembers()->create([
        'user_id' => $memberUser->id,
        'role' => $currentRole->name,
    ]);
    Membership::create([
        'user_id' => $memberUser->id,
        'role_id' => $currentRole->id,
        'scope_id' => $project->id,
        'scope_type' => 'project',
    ]);

    $response = $this->actingAs($user)->patch(
        route('projects.members.role.update', [$team, $project, $projectMember]),
        ['role_id' => $newRole->id],
    );

    $response->assertRedirect(route('projects.members.index', [$team, $project]));
    $this->assertDatabaseHas('memberships', [
        'user_id' => $memberUser->id,
        'role_id' => $newRole->id,
        'scope_id' => $project->id,
        'scope_type' => 'project',
    ]);
    $this->assertDatabaseHas('project_members', [
        'id' => $projectMember->id,
        'role' => $newRole->name,
    ]);
});

test('project owner can remove a member without removing them from the team', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectMemberManagementFixture();
    $memberUser = addProjectMemberManagementTeamMember($team);
    $projectMember = $project->projectMembers()->create([
        'user_id' => $memberUser->id,
        'role' => $role->name,
    ]);
    Membership::create([
        'user_id' => $memberUser->id,
        'role_id' => $role->id,
        'scope_id' => $project->id,
        'scope_type' => 'project',
    ]);

    $response = $this->actingAs($user)->delete(
        route('projects.members.destroy', [$team, $project, $projectMember]),
    );

    $response->assertRedirect(route('projects.members.index', [$team, $project]));
    $this->assertDatabaseMissing('project_members', ['id' => $projectMember->id]);
    $this->assertDatabaseMissing('memberships', [
        'user_id' => $memberUser->id,
        'scope_id' => $project->id,
        'scope_type' => 'project',
    ]);
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $memberUser->id,
    ]);
});

test('project member without own permission cannot manage project members', function () {
    ['team' => $team, 'project' => $project, 'user' => $user] = createProjectMemberManagementFixture(ResourceGrants::READ);

    $response = $this->actingAs($user)->get(route('projects.members.index', [$team, $project]));

    $response->assertForbidden();
});

test('project owner cannot manage members through a different team route', function () {
    ['team' => $team, 'user' => $user] = createProjectMemberManagementFixture();
    $otherTeam = Team::create([
        'name' => 'Another Team',
        'slug' => 'another-team-'.$user->id,
        'owner_id' => $user->id,
    ]);
    $otherProject = $otherTeam->projects()->create([
        'owner_id' => $user->id,
        'name' => 'Another Project',
        'slug' => 'another-project-'.$user->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('projects.members.index', [$team, $otherProject]));

    $response->assertNotFound();
});
