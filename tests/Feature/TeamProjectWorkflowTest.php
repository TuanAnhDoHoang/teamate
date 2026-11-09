<?php

use App\Models\Membership;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ProjectMember;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{team: Team, project: Project, member: User}
 */
function createProjectReadFixture(?int $teamPermissionMask, ?int $projectPermissionMask): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::create([
        'name' => 'Project Access Team',
        'slug' => 'project-access-team-'.$member->id,
        'owner_id' => $owner->id,
    ]);

    TeamMember::create(['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'reviewer']);

    $role = Roles::create([
        'code' => 'team_project_reader_'.$member->id,
        'name' => 'Project Reader',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create(['user_id' => $member->id, 'role_id' => $role->id, 'scope_id' => $team->id]);

    if ($teamPermissionMask !== null) {
        $teamResourceType = ResourceTypes::firstOrCreate(['code' => 'team']);
        ResourceGrants::create([
            'role_id' => $role->id,
            'resource_type_id' => $teamResourceType->id,
            'resource_specific' => $team->id,
            'permission_mask' => $teamPermissionMask,
            'granted_by' => $owner->id,
        ]);
    }

    $project = $team->projects()->create([
        'owner_id' => $owner->id,
        'name' => 'Project Access',
        'slug' => 'project-access',
        'status' => 'active',
    ]);
    $projectRole = Roles::create([
        'code' => 'project_'.$project->id.'_reader',
        'name' => 'Project Reader',
        'scope_type' => 'project',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create(['user_id' => $member->id, 'role_id' => $projectRole->id, 'scope_id' => $project->id]);
    ProjectMember::create([
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => $projectRole->name,
    ]);

    if ($projectPermissionMask !== null) {
        $projectResourceType = ResourceTypes::firstOrCreate(['code' => 'project']);
        ResourceGrants::create([
            'role_id' => $projectRole->id,
            'resource_type_id' => $projectResourceType->id,
            'resource_specific' => $project->id,
            'permission_mask' => $projectPermissionMask,
            'granted_by' => $owner->id,
        ]);
    }

    return compact('team', 'project', 'member');
}

function createProjectItemWithGrant(Project $project, User $user, string $kind, int $permissionMask, string $name): ProjectItem
{
    $item = $project->items()->create([
        'kind' => $kind,
        'name' => $name,
        'size_bytes' => 0,
        'created_by' => $user->id,
    ]);
    $projectRoleId = Membership::query()
        ->where('user_id', $user->id)
        ->where('scope_id', $project->id)
        ->whereHas('role', fn ($query) => $query->where('scope_type', 'project'))
        ->value('role_id');
    $resourceType = ResourceTypes::firstOrCreate(['code' => $kind]);

    ResourceGrants::create([
        'role_id' => $projectRoleId,
        'resource_type_id' => $resourceType->id,
        'resource_specific' => $item->id,
        'permission_mask' => $permissionMask,
        'granted_by' => $user->id,
    ]);

    return $item;
}

test('authenticated user can view dashboard with empty state or team list', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee("You don't have any teams yet");

    // Create a team
    $team = Team::create([
        'name' => 'Acme Security',
        'slug' => 'acme-security',
        'owner_id' => $user->id,
    ]);
    TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'role' => 'admin',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Acme Security');
    $response->assertSee('Admin');
});

test('user can create a team and gets attached as admin', function () {
    $user = User::factory()->create();
    DB::table('resource_types')->where('code', 'team')->delete();

    $response = $this->actingAs($user)->post(route('teams.store'), [
        'name' => 'Kernel Devs',
        'slug' => 'kernel-devs',
    ]);

    $team = Team::where('slug', 'kernel-devs')->first();
    expect($team)->not->toBeNull();
    expect($team->owner_id)->toBe($user->id);

    $membership = DB::table('memberships')->where([
        'user_id' => $user->id,
        'scope_id' => $team->id,
    ])->first();
    expect($membership)->not->toBeNull();
    expect(DB::table('roles')->where('id', $membership->role_id)->value('code'))
        ->toBe('team_administrator_kernel-devs');

    $teamResourceTypeId = DB::table('resource_types')->where('code', 'team')->value('id');
    expect($teamResourceTypeId)->not->toBeNull();

    $projectResourceTypeId = DB::table('resource_types')->where('code', 'project')->value('id');
    expect($projectResourceTypeId)->not->toBeNull();
    expect(DB::table('resource_grants')->where([
        'role_id' => $membership->role_id,
        'resource_type_id' => $projectResourceTypeId,
        'resource_specific' => $team->id,
        'permission_mask' => 7,
        'granted_by' => $user->id,
    ])->exists())->toBeTrue();

    expect(DB::table('resource_grants')->where([
        'role_id' => $membership->role_id,
        'resource_type_id' => $teamResourceTypeId,
        'resource_specific' => $team->id,
        'permission_mask' => 7,
        'granted_by' => $user->id,
    ])->exists())->toBeTrue();

    $member = TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->first();
    expect($member)->not->toBeNull();
    expect($member->role)->toBe('admin');

    $response->assertRedirect(route('teams.show', $team));
});

test('team page renders projects and members tabs with permissions', function () {
    $owner = User::factory()->create();
    $client = User::factory()->create();

    $team = Team::create([
        'name' => 'CyberOps',
        'slug' => 'cyberops',
        'owner_id' => $owner->id,
    ]);

    TeamMember::create(['team_id' => $team->id, 'user_id' => $owner->id, 'role' => 'admin']);
    TeamMember::create(['team_id' => $team->id, 'user_id' => $client->id, 'role' => 'client']);

    $teamResourceType = ResourceTypes::create(['code' => 'team']);
    $ownerRole = Roles::create([
        'code' => 'team_administrator_cyberops',
        'name' => 'Team Administrator',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    $clientRole = Roles::create([
        'code' => 'team_client_cyberops',
        'name' => 'Team Client',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);

    Membership::create(['user_id' => $owner->id, 'role_id' => $ownerRole->id, 'scope_id' => $team->id]);
    Membership::create(['user_id' => $client->id, 'role_id' => $clientRole->id, 'scope_id' => $team->id]);

    ResourceGrants::create([
        'role_id' => $ownerRole->id,
        'resource_type_id' => $teamResourceType->id,
        'resource_specific' => $team->id,
        'permission_mask' => 7,
        'granted_by' => $owner->id,
    ]);
    ResourceGrants::create([
        'role_id' => $clientRole->id,
        'resource_type_id' => $teamResourceType->id,
        'resource_specific' => $team->id,
        'permission_mask' => 4,
        'granted_by' => $owner->id,
    ]);

    // Owner sees Create project button
    $response = $this->actingAs($owner)->get(route('teams.show', $team));
    $response->assertOk();
    $response->assertSee('Create project');
    $response->assertSee('No projects in this team yet');

    // Client does NOT see Create project button
    $responseClient = $this->actingAs($client)->get(route('teams.show', $team));
    $responseClient->assertOk();
    $responseClient->assertDontSee('Create project');

    // Client cannot POST to create project
    $this->actingAs($client)->post(route('teams.projects.store', $team), [
        'name' => 'Client Hacked Project',
    ])->assertForbidden();

    // Owner can create project
    $createProjectResponse = $this->actingAs($owner)->post(route('teams.projects.store', $team), [
        'name' => 'Vault Core',
        'description' => 'Key lifecycle management engine',
    ]);

    $project = Project::where('team_id', $team->id)->where('slug', 'vault-core')->first();
    expect($project)->not->toBeNull();
    $createProjectResponse->assertRedirect(route('projects.show', [$team, $project]));

    $projectRoleMembership = Membership::query()
        ->where('user_id', $owner->id)
        ->where('scope_id', $project->id)
        ->whereHas('role', fn ($query) => $query->where('scope_type', 'project'))
        ->firstOrFail();
    $this->assertDatabaseHas('memberships', [
        'user_id' => $owner->id,
        'role_id' => $projectRoleMembership->role_id,
        'scope_id' => $project->id,
    ]);

    $projectResourceType = ResourceTypes::where('code', 'project')->firstOrFail();

    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $projectRoleMembership->role_id,
        'resource_type_id' => $projectResourceType->id,
        'resource_specific' => $project->id,
        'permission_mask' => 7,
        'granted_by' => $owner->id,
    ]);
    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'role' => 'Team Administrator',
    ]);
    $this->actingAs($owner)
        ->get(route('projects.show', [$team, $project]))
        ->assertOk();
});

test('team member with a read grant for the team can view the team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::create([
        'name' => 'Readable Team',
        'slug' => 'readable-team',
        'owner_id' => $owner->id,
    ]);

    TeamMember::create(['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'client']);
    $role = Roles::create([
        'code' => 'team_reader_readable-team',
        'name' => 'Team Reader',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create(['user_id' => $member->id, 'role_id' => $role->id, 'scope_id' => $team->id]);
    $resourceType = ResourceTypes::create(['code' => 'team']);
    ResourceGrants::create([
        'role_id' => $role->id,
        'resource_type_id' => $resourceType->id,
        'resource_specific' => $team->id,
        'permission_mask' => 4,
        'granted_by' => $owner->id,
    ]);

    $response = $this->actingAs($member)->get(route('teams.show', $team));

    $response->assertOk();
});

test('user without team membership cannot use a team read grant', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = Team::create([
        'name' => 'Private Team',
        'slug' => 'private-team',
        'owner_id' => $owner->id,
    ]);

    $role = Roles::create([
        'code' => 'team_outsider_private-team',
        'name' => 'Team Outsider',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create(['user_id' => $outsider->id, 'role_id' => $role->id, 'scope_id' => $team->id]);
    $resourceType = ResourceTypes::create(['code' => 'team']);
    ResourceGrants::create([
        'role_id' => $role->id,
        'resource_type_id' => $resourceType->id,
        'resource_specific' => $team->id,
        'permission_mask' => 4,
        'granted_by' => $owner->id,
    ]);

    $response = $this->actingAs($outsider)->get(route('teams.show', $team));

    $response->assertForbidden();
});

test('team member without a read grant for the team cannot view the team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::create([
        'name' => 'No Read Team',
        'slug' => 'no-read-team',
        'owner_id' => $owner->id,
    ]);

    TeamMember::create(['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'client']);
    $role = Roles::create([
        'code' => 'team_nonreader_no-read-team',
        'name' => 'Team Nonreader',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create(['user_id' => $member->id, 'role_id' => $role->id, 'scope_id' => $team->id]);

    $response = $this->actingAs($member)->get(route('teams.show', $team));

    $response->assertForbidden();
});

test('permission masks decode to own write and read permissions', function () {
    expect(ResourceGrants::decodePermissionMask(1))->toBe(['own' => 'o']);
    expect(ResourceGrants::decodePermissionMask(2))->toBe(['write' => 'w']);
    expect(ResourceGrants::decodePermissionMask(4))->toBe(['read' => 'r']);
    expect(ResourceGrants::decodePermissionMask(7))->toBe([
        'own' => 'o',
        'write' => 'w',
        'read' => 'r',
    ]);

    expect(ResourceGrants::hasPermission(2, ResourceGrants::WRITE))->toBeTrue();
    expect(ResourceGrants::hasPermission(4, ResourceGrants::WRITE))->toBeFalse();
});

test('team member with write permission can add a guest member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::create([
        'name' => 'Write Team',
        'slug' => 'write-team',
        'owner_id' => $owner->id,
    ]);

    TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'role' => 'admin',
    ]);

    $adminRole = Roles::create([
        'code' => 'team_administrator_write-team',
        'name' => 'Team Administrator',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);

    Membership::create([
        'user_id' => $owner->id,
        'role_id' => $adminRole->id,
        'scope_id' => $team->id,
    ]);

    $resourceType = ResourceTypes::create(['code' => 'team_member']);
    ResourceGrants::create([
        'role_id' => $adminRole->id,
        'resource_type_id' => $resourceType->id,
        'resource_specific' => $team->id,
        'permission_mask' => 2,
        'granted_by' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->post(route('teams.members.store', $team), [
        'email' => $member->email,
    ]);

    $response->assertRedirect(route('teams.show', $team));

    $teamMember = TeamMember::where('team_id', $team->id)
        ->where('user_id', $member->id)
        ->first();

    expect($teamMember)->not->toBeNull();
    expect($teamMember->role)->toBe('guest');

    $guestRole = Roles::where('code', 'guest')
        ->where('scope_type', 'team')
        ->first();

    expect($guestRole)->not->toBeNull();
    $this->assertDatabaseHas('memberships', [
        'user_id' => $member->id,
        'role_id' => $guestRole->id,
        'scope_id' => $team->id,
    ]);
});

test('team member without write permission cannot add a member', function () {
    $owner = User::factory()->create();
    $writer = User::factory()->create();
    $candidate = User::factory()->create();

    $team = Team::create([
        'name' => 'Read Only Team',
        'slug' => 'read-only-team',
        'owner_id' => $owner->id,
    ]);

    TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'role' => 'admin',
    ]);
    TeamMember::create([
        'team_id' => $team->id,
        'user_id' => $writer->id,
        'role' => 'review',
    ]);

    $reviewRole = Roles::create([
        'code' => 'team_reviewer_read-only-team',
        'name' => 'Team Reviewer',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create([
        'user_id' => $writer->id,
        'role_id' => $reviewRole->id,
        'scope_id' => $team->id,
    ]);

    $resourceType = ResourceTypes::create(['code' => 'team_member']);
    ResourceGrants::create([
        'role_id' => $reviewRole->id,
        'resource_type_id' => $resourceType->id,
        'resource_specific' => $team->id,
        'permission_mask' => 4,
        'granted_by' => $owner->id,
    ]);

    $response = $this->actingAs($writer)->post(route('teams.members.store', $team), [
        'email' => $candidate->email,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('team_members', [
        'team_id' => $team->id,
        'user_id' => $candidate->id,
    ]);
});

test('project page renders empty drop zone, allows upload and folder creation', function () {
    Storage::fake('local');
    $owner = User::factory()->create();

    $team = Team::create(['name' => 'DevOps', 'slug' => 'devops', 'owner_id' => $owner->id]);
    TeamMember::create(['team_id' => $team->id, 'user_id' => $owner->id, 'role' => 'admin']);
    $role = Roles::create([
        'code' => 'team_administrator_devops',
        'name' => 'Admin',
        'scope_type' => 'team',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create(['user_id' => $owner->id, 'role_id' => $role->id, 'scope_id' => $team->id]);
    $teamResourceType = ResourceTypes::create(['code' => 'team']);
    ResourceGrants::create([
        'role_id' => $role->id,
        'resource_type_id' => $teamResourceType->id,
        'resource_specific' => $team->id,
        'permission_mask' => 7,
        'granted_by' => $owner->id,
    ]);
    $project = $team->projects()->create([
        'owner_id' => $owner->id,
        'name' => 'Decentralized Walrus Node',
        'slug' => 'decentralized-walrus-node',
        'status' => 'active',
    ]);
    $projectRole = Roles::create([
        'code' => 'project_'.$project->id.'_administrator',
        'name' => 'Project Administrator',
        'scope_type' => 'project',
        'team_id' => $team->id,
        'is_system' => false,
    ]);
    Membership::create(['user_id' => $owner->id, 'role_id' => $projectRole->id, 'scope_id' => $project->id]);
    ProjectMember::create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'role' => $projectRole->name,
    ]);
    ResourceGrants::create([
        'role_id' => $projectRole->id,
        'resource_type_id' => ResourceTypes::create(['code' => 'project'])->id,
        'resource_specific' => $project->id,
        'permission_mask' => 7,
        'granted_by' => $owner->id,
    ]);

    // Check project empty view
    $response = $this->actingAs($owner)->get(route('projects.show', [$team, $project]));
    $response->assertOk();
    $response->assertSee('This project is empty');
    $response->assertSee('Upload files');

    // Create a folder
    $this->actingAs($owner)->post(route('projects.folders.store', [$team, $project]), [
        'name' => 'source-code',
    ])->assertRedirect();

    $folder = ProjectItem::where('project_id', $project->id)->where('name', 'source-code')->first();
    expect($folder)->not->toBeNull();
    expect($folder->isFolder())->toBeTrue();
    $projectMembership = Membership::query()
        ->where('user_id', $owner->id)
        ->where('scope_id', $project->id)
        ->whereHas('role', fn ($query) => $query->where('scope_type', 'project'))
        ->firstOrFail();
    $folderResourceType = ResourceTypes::where('code', 'folder')->firstOrFail();
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $projectMembership->role_id,
        'resource_type_id' => $folderResourceType->id,
        'resource_specific' => $folder->id,
        'permission_mask' => 7,
        'granted_by' => $owner->id,
    ]);

    // Upload a file via AJAX
    $file = UploadedFile::fake()->create('kernel.rs', 1024, 'text/plain');

    $uploadResponse = $this->actingAs($owner)->postJson(route('projects.upload', [$team, $project]), [
        'file' => $file,
        'parent_id' => $folder->id,
        'relative_path' => 'source-code/kernel.rs',
    ]);

    $uploadResponse->assertOk()->assertJson(['success' => true]);

    $uploadedItem = ProjectItem::where('project_id', $project->id)->where('name', 'kernel.rs')->first();
    expect($uploadedItem)->not->toBeNull();
    expect($uploadedItem->isFile())->toBeTrue();
    expect($uploadedItem->parent_id)->toBe($folder->id);
    $fileResourceType = ResourceTypes::where('code', 'file')->firstOrFail();
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $projectMembership->role_id,
        'resource_type_id' => $fileResourceType->id,
        'resource_specific' => $uploadedItem->id,
        'permission_mask' => 7,
        'granted_by' => $owner->id,
    ]);

    $this->actingAs($owner)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->create('readme.txt', 10, 'text/plain'),
        'relative_path' => 'uploaded-folder/readme.txt',
    ])->assertOk()->assertJson(['success' => true]);

    $uploadedFolder = ProjectItem::where('project_id', $project->id)
        ->where('kind', 'folder')
        ->where('name', 'uploaded-folder')
        ->firstOrFail();
    $this->assertDatabaseHas('resource_grants', [
        'role_id' => $projectMembership->role_id,
        'resource_type_id' => $folderResourceType->id,
        'resource_specific' => $uploadedFolder->id,
        'permission_mask' => 7,
        'granted_by' => $owner->id,
    ]);

    // Download file
    $downloadResponse = $this->actingAs($owner)->get(route('projects.items.download', [$team, $project, $uploadedItem]));
    $downloadResponse->assertOk();
});

test('team member with read permission on the team and project can view the project', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );

    $response = $this->actingAs($member)->get(route('projects.show', [$team, $project]));

    $response->assertOk();
});

test('team member without project read permission cannot view the project', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        null,
    );

    $response = $this->actingAs($member)->get(route('projects.show', [$team, $project]));

    $response->assertForbidden();
});

test('team member without team read permission cannot view the project', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        null,
        ResourceGrants::READ,
    );

    $response = $this->actingAs($member)->get(route('projects.show', [$team, $project]));

    $response->assertForbidden();
});

test('project member without write permission cannot upload files', function () {
    Storage::fake('local');
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );

    $response = $this->actingAs($member)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->create('forbidden.txt', 10, 'text/plain'),
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'forbidden.txt',
    ]);
    Storage::disk('local')->assertDirectoryEmpty("projects/{$project->id}");
});

test('project member without write permission on the parent folder cannot upload files', function () {
    Storage::fake('local');
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::WRITE,
    );
    $roleId = Membership::query()
        ->where('user_id', $member->id)
        ->where('scope_id', $project->id)
        ->value('role_id');
    $folder = $project->items()->create([
        'kind' => 'folder',
        'name' => 'restricted',
        'size_bytes' => 0,
        'created_by' => $member->id,
    ]);
    $folderResourceType = ResourceTypes::firstOrCreate(['code' => 'folder']);
    ResourceGrants::create([
        'role_id' => $roleId,
        'resource_type_id' => $folderResourceType->id,
        'resource_specific' => $folder->id,
        'permission_mask' => ResourceGrants::READ,
        'granted_by' => $member->id,
    ]);

    $response = $this->actingAs($member)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->create('forbidden.txt', 10, 'text/plain'),
        'parent_id' => $folder->id,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'forbidden.txt',
    ]);
    Storage::disk('local')->assertDirectoryEmpty("projects/{$project->id}");
});

test('project member without file write permission cannot rename a file', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::WRITE,
    );
    $file = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::READ, 'original.txt');

    $response = $this->actingAs($member)->patch(
        route('projects.items.rename', [$team, $project, $file]),
        ['name' => 'renamed.txt'],
    );

    $response->assertForbidden();
    $this->assertDatabaseHas('project_items', [
        'id' => $file->id,
        'name' => 'original.txt',
    ]);
});

test('project member without folder write permission cannot delete a folder', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::WRITE,
    );
    $folder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::READ, 'restricted');

    $response = $this->actingAs($member)->delete(
        route('projects.items.destroy', [$team, $project, $folder]),
    );

    $response->assertForbidden();
    $this->assertDatabaseHas('project_items', [
        'id' => $folder->id,
        'deleted_at' => null,
    ]);
});

test('project member with item write permission can rename and delete items', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::WRITE,
    );
    $file = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::WRITE, 'original.txt');
    $folder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::WRITE, 'temporary');

    $renameResponse = $this->actingAs($member)->patch(
        route('projects.items.rename', [$team, $project, $file]),
        ['name' => 'renamed.txt'],
    );
    $deleteResponse = $this->actingAs($member)->delete(
        route('projects.items.destroy', [$team, $project, $folder]),
    );

    $renameResponse->assertRedirect();
    $deleteResponse->assertRedirect();
    $this->assertDatabaseHas('project_items', [
        'id' => $file->id,
        'name' => 'renamed.txt',
    ]);
    $this->assertSoftDeleted('project_items', ['id' => $folder->id]);
});

test('project member without file read permission cannot download a file', function () {
    Storage::fake('local');
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $file = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::WRITE, 'private.txt');
    $file->update([
        'storage_backend' => 'local',
        'storage_ref' => 'projects/'.$project->id.'/private.txt',
    ]);
    Storage::disk('local')->put($file->storage_ref, 'secret contents');

    $response = $this->actingAs($member)->get(
        route('projects.items.download', [$team, $project, $file]),
    );

    $response->assertForbidden();
});

test('project member with file read permission can download a file', function () {
    Storage::fake('local');
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $file = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::READ, 'readable.txt');
    $file->update([
        'storage_backend' => 'local',
        'storage_ref' => 'projects/'.$project->id.'/readable.txt',
    ]);
    Storage::disk('local')->put($file->storage_ref, 'file contents');

    $response = $this->actingAs($member)->get(
        route('projects.items.download', [$team, $project, $file]),
    );

    $response->assertOk();
    $response->assertDownload('readable.txt');
});
