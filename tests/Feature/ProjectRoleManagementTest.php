<?php

use App\Models\Membership;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ProjectMember;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\User;

/**
 * @return array{team: Team, project: Project, user: User, role: Roles, resourceTypes: array<string, ResourceTypes>}
 */
function createProjectRoleManagementFixture(int $projectPermission = 7): array
{
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Project Role Team',
        'slug' => 'project-role-team-'.$user->id,
        'owner_id' => $user->id,
    ]);
    $project = $team->projects()->create([
        'owner_id' => $user->id,
        'name' => 'Project Role Project',
        'slug' => 'project-role-project-'.$user->id,
        'status' => 'active',
    ]);

    $role = Roles::create([
        'code' => 'project_'.$project->id.'_member',
        'name' => 'Project Member',
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

    $resourceTypes = [];

    foreach (['project', 'file', 'folder'] as $resourceTypeCode) {
        $resourceTypes[$resourceTypeCode] = ResourceTypes::firstOrCreate(['code' => $resourceTypeCode]);
    }

    ResourceGrants::create([
        'role_id' => $role->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $project->id,
        'permission_mask' => $projectPermission,
        'granted_by' => $user->id,
    ]);

    return compact('team', 'project', 'user', 'role', 'resourceTypes');
}

function createProjectRoleManagementItem(
    Project $project,
    User $user,
    Roles $role,
    string $kind,
    int $permissionMask,
    string $name,
): ProjectItem {
    $item = $project->items()->create([
        'kind' => $kind,
        'name' => $name,
        'size_bytes' => 0,
        'created_by' => $user->id,
    ]);
    $resourceType = ResourceTypes::firstOrCreate(['code' => $kind]);

    ResourceGrants::create([
        'role_id' => $role->id,
        'resource_type_id' => $resourceType->id,
        'resource_specific' => $item->id,
        'permission_mask' => $permissionMask,
        'granted_by' => $user->id,
    ]);

    return $item;
}

test('project role writer can view only resources they own', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectRoleManagementFixture();
    $file = createProjectRoleManagementItem($project, $user, $role, 'file', ResourceGrants::OWN, 'owned.txt');
    $folder = createProjectRoleManagementItem($project, $user, $role, 'folder', ResourceGrants::OWN, 'owned-folder');
    $hiddenFile = createProjectRoleManagementItem($project, $user, $role, 'file', ResourceGrants::READ, 'not-owned.txt');

    $response = $this->actingAs($user)->get(route('projects.roles.index', [$team, $project]));

    $response->assertOk();
    $response->assertSee('permissions[project]['.$project->id.']');
    $response->assertSee('permissions[file]['.$file->id.']');
    $response->assertSee('permissions[folder]['.$folder->id.']');
    $response->assertDontSee('not-owned.txt (#'.$hiddenFile->id.')');
});

test('project role resources are nested under their parent folder', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectRoleManagementFixture();
    $folder = createProjectRoleManagementItem($project, $user, $role, 'folder', ResourceGrants::OWN, 'owned-folder');
    $file = createProjectRoleManagementItem($project, $user, $role, 'file', ResourceGrants::OWN, 'nested.txt');
    $file->update(['parent_id' => $folder->id]);

    $response = $this->actingAs($user)->get(route('projects.roles.index', [$team, $project]));

    $response->assertOk()
        ->assertSee('Folder: owned-folder (#'.$folder->id.')')
        ->assertSee('File: nested.txt (#'.$file->id.')')
        ->assertSee('permissions[file]['.$file->id.']');
});

test('project role management requires authentication', function () {
    ['team' => $team, 'project' => $project] = createProjectRoleManagementFixture();

    $response = $this->get(route('projects.roles.index', [$team, $project]));

    $response->assertRedirect(route('login'));
});

test('project role writer can create a role with permissions for owned project resources', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role, 'resourceTypes' => $resourceTypes] = createProjectRoleManagementFixture();
    $file = createProjectRoleManagementItem($project, $user, $role, 'file', ResourceGrants::OWN, 'owned.txt');
    $folder = createProjectRoleManagementItem($project, $user, $role, 'folder', ResourceGrants::OWN, 'owned-folder');

    $response = $this->actingAs($user)->post(route('projects.roles.store', [$team, $project]), [
        'name' => 'Project Contributor',
        'permissions' => [
            'project' => [$project->id => ResourceGrants::READ],
            'file' => [$file->id => ResourceGrants::WRITE],
            'folder' => [$folder->id => 7],
        ],
    ]);

    $response->assertRedirect(route('projects.roles.index', [$team, $project]));
    $role = Roles::query()
        ->where('code', 'project_'.$project->id.'_project-contributor')
        ->firstOrFail();

    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $role->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $project->id,
        'permission_mask' => ResourceGrants::READ,
        'granted_by' => $user->id,
    ]);
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $role->id,
        'resource_type_id' => $resourceTypes['file']->id,
        'resource_specific' => $file->id,
        'permission_mask' => ResourceGrants::WRITE,
        'granted_by' => $user->id,
    ]);
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $role->id,
        'resource_type_id' => $resourceTypes['folder']->id,
        'resource_specific' => $folder->id,
        'permission_mask' => 7,
        'granted_by' => $user->id,
    ]);
});

test('project role writer can apply a folder permission to all current descendants', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $memberRole, 'resourceTypes' => $resourceTypes] = createProjectRoleManagementFixture();
    $folder = createProjectRoleManagementItem($project, $user, $memberRole, 'folder', ResourceGrants::OWN, 'parent-folder');
    $nestedFolder = createProjectRoleManagementItem($project, $user, $memberRole, 'folder', ResourceGrants::OWN, 'nested-folder');
    $nestedFolder->update(['parent_id' => $folder->id]);
    $file = createProjectRoleManagementItem($project, $user, $memberRole, 'file', ResourceGrants::OWN, 'nested.txt');
    $file->update(['parent_id' => $nestedFolder->id]);

    $response = $this->actingAs($user)->post(route('projects.roles.store', [$team, $project]), [
        'name' => 'Folder Reader',
        'permissions' => [
            'folder' => [
                $folder->id => ResourceGrants::READ,
                $nestedFolder->id => ResourceGrants::WRITE,
            ],
            'file' => [$file->id => ResourceGrants::OWN],
        ],
        'apply_to_descendants' => [$folder->id => '1'],
    ]);

    $response->assertRedirect(route('projects.roles.index', [$team, $project]));
    $newRole = Roles::query()
        ->where('code', 'project_'.$project->id.'_folder-reader')
        ->firstOrFail();

    foreach ([
        [$resourceTypes['folder']->id, $folder->id],
        [$resourceTypes['folder']->id, $nestedFolder->id],
        [$resourceTypes['file']->id, $file->id],
    ] as [$resourceTypeId, $resourceId]) {
        $this->assertDatabaseHas('resource_grants', [
            'role_id' => $newRole->id,
            'resource_type_id' => $resourceTypeId,
            'resource_specific' => $resourceId,
            'permission_mask' => ResourceGrants::READ,
            'granted_by' => $user->id,
        ]);
    }
});

test('project role writer cannot bulk grant permissions through a folder they do not own', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectRoleManagementFixture();
    $ownedFolder = createProjectRoleManagementItem($project, $user, $role, 'folder', ResourceGrants::OWN, 'owned-folder');
    $folder = createProjectRoleManagementItem($project, $user, $role, 'folder', ResourceGrants::READ, 'not-owned-folder');

    $response = $this->actingAs($user)->post(route('projects.roles.store', [$team, $project]), [
        'name' => 'Unauthorized Folder Role',
        'permissions' => ['folder' => [$ownedFolder->id => ResourceGrants::READ]],
        'apply_to_descendants' => [$folder->id => '1'],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('roles', [
        'team_id' => $team->id,
        'name' => 'Unauthorized Folder Role',
    ]);
});

test('project role writer can update role name and permissions on owned resources', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $memberRole, 'resourceTypes' => $resourceTypes] = createProjectRoleManagementFixture();
    $file = createProjectRoleManagementItem($project, $user, $memberRole, 'file', ResourceGrants::OWN, 'owned.txt');
    $projectRole = Roles::create([
        'code' => 'project_'.$project->id.'_reviewer',
        'name' => 'Reviewer',
        'scope_type' => 'project',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    ResourceGrants::create([
        'role_id' => $projectRole->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $project->id,
        'permission_mask' => ResourceGrants::READ,
        'granted_by' => $user->id,
    ]);
    ResourceGrants::create([
        'role_id' => $projectRole->id,
        'resource_type_id' => $resourceTypes['file']->id,
        'resource_specific' => $file->id,
        'permission_mask' => ResourceGrants::READ,
        'granted_by' => $user->id,
    ]);

    $memberUser = User::factory()->create();
    Membership::create([
        'user_id' => $memberUser->id,
        'role_id' => $projectRole->id,
        'scope_id' => $project->id,
        'scope_type' => 'project',
    ]);
    ProjectMember::create([
        'project_id' => $project->id,
        'user_id' => $memberUser->id,
        'role' => $projectRole->name,
    ]);

    $response = $this->actingAs($user)->put(route('projects.roles.update', [$team, $project, $projectRole]), [
        'name' => 'Project Reviewer',
        'permissions' => [
            'project' => [$project->id => 0],
            'file' => [$file->id => ResourceGrants::WRITE],
        ],
    ]);

    $response->assertRedirect(route('projects.roles.index', [$team, $project]));
    $this->assertDatabaseHas('roles', [
        'id' => $projectRole->id,
        'name' => 'Project Reviewer',
    ]);
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $projectRole->id,
        'resource_type_id' => $resourceTypes['file']->id,
        'resource_specific' => $file->id,
        'permission_mask' => ResourceGrants::WRITE,
        'granted_by' => $user->id,
    ]);
    $this->assertDatabaseMissing('resource_grants', [
        'role_id' => $projectRole->id,
        'resource_type_id' => $resourceTypes['project']->id,
        'resource_specific' => $project->id,
    ]);
    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $memberUser->id,
        'role' => 'Project Reviewer',
    ]);
});

test('project role writer cannot grant permissions for resources they do not own', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectRoleManagementFixture();
    $file = createProjectRoleManagementItem($project, $user, $role, 'file', ResourceGrants::READ, 'read-only.txt');

    $response = $this->actingAs($user)->post(route('projects.roles.store', [$team, $project]), [
        'name' => 'Unauthorized Role',
        'permissions' => [
            'file' => [$file->id => ResourceGrants::WRITE],
        ],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('roles', [
        'team_id' => $team->id,
        'name' => 'Unauthorized Role',
    ]);
});

test('project member without own permission cannot manage project roles', function () {
    ['team' => $team, 'project' => $project, 'user' => $user] = createProjectRoleManagementFixture(ResourceGrants::READ);

    $response = $this->actingAs($user)->get(route('projects.roles.index', [$team, $project]));

    $response->assertForbidden();
});

test('project role writer cannot manage roles through a different team route', function () {
    ['team' => $team, 'user' => $user] = createProjectRoleManagementFixture();
    $otherTeam = Team::create([
        'name' => 'Other Project Role Team',
        'slug' => 'other-project-role-team-'.$user->id,
        'owner_id' => $user->id,
    ]);
    $otherProject = $otherTeam->projects()->create([
        'owner_id' => $user->id,
        'name' => 'Other Project',
        'slug' => 'other-project-'.$user->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('projects.roles.index', [$team, $otherProject]));

    $response->assertNotFound();
});

test('project role writer cannot submit an invalid permission mask', function () {
    ['team' => $team, 'project' => $project, 'user' => $user, 'role' => $role] = createProjectRoleManagementFixture();
    $file = createProjectRoleManagementItem($project, $user, $role, 'file', ResourceGrants::OWN, 'owned.txt');

    $response = $this->actingAs($user)->post(route('projects.roles.store', [$team, $project]), [
        'name' => 'Invalid Role',
        'permissions' => [
            'file' => [$file->id => 8],
        ],
    ]);

    $response->assertSessionHasErrors(['permissions.file.'.$file->id]);
    $this->assertDatabaseMissing('roles', [
        'team_id' => $team->id,
        'name' => 'Invalid Role',
    ]);
});
