<?php

use App\Exceptions\ClamAvScanException;
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
use App\Services\ClamAvScanner;
use App\Services\VaultFileEncryption;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

function configureTeamProjectWorkflowVault(): void
{
    config([
        'services.vault.address' => 'http://vault.test',
        'services.vault.token' => 'test-vault-token',
        'services.vault.transit_key' => 'teamate-files',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://vault.test/v1/transit/datakey/plaintext/teamate-files' => Http::response([
            'data' => [
                'plaintext' => base64_encode(str_repeat('k', 32)),
                'ciphertext' => 'vault:v1:d3JhcHBlZC1kYXRhLWtleQ==',
            ],
        ]),
        'http://vault.test/v1/transit/decrypt/teamate-files' => Http::response([
            'data' => [
                'plaintext' => base64_encode(str_repeat('k', 32)),
            ],
        ]),
    ]);
}

function fakeProjectClamAvScan(?string $signature = null, int $calls = 1): void
{
    $scanner = Mockery::mock(ClamAvScanner::class);
    $scanner->shouldReceive('scan')
        ->times($calls)
        ->andReturn($signature);

    app()->instance(ClamAvScanner::class, $scanner);
}

function storeEncryptedTeamProjectTestFile(ProjectItem $item, string $contents): void
{
    $plaintextPath = tempnam(sys_get_temp_dir(), 'teamate-test-plaintext-');
    $encryptedPath = tempnam(sys_get_temp_dir(), 'teamate-test-encrypted-');

    if ($plaintextPath === false || $encryptedPath === false) {
        throw new RuntimeException('Unable to create temporary files for a test.');
    }

    try {
        file_put_contents($plaintextPath, $contents);
        $metadata = app(VaultFileEncryption::class)->encryptFile($plaintextPath, $encryptedPath);
        $storageRef = "projects/{$item->project_id}/{$item->id}.enc";
        $stream = fopen($encryptedPath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open the encrypted test file.');
        }

        try {
            Storage::disk('local')->put($storageRef, $stream);
        } finally {
            fclose($stream);
        }

        $item->update([
            'storage_backend' => 'local',
            'storage_ref' => $storageRef,
            'encryption_key_ref' => $metadata['key_ref'],
            'encryption_key_version' => $metadata['key_version'],
            'size_bytes' => strlen($contents),
            'checksum_sha256' => hash_file('sha256', $encryptedPath),
        ]);
    } finally {
        if (is_file($plaintextPath)) {
            unlink($plaintextPath);
        }

        if (is_file($encryptedPath)) {
            unlink($encryptedPath);
        }
    }
}

test('user can have team and project memberships with the same scope id', function () {
    $user = User::factory()->create();
    $teamRole = Roles::create([
        'code' => 'team_same_scope_'.$user->id,
        'name' => 'Team Member',
        'scope_type' => 'team',
        'is_system' => false,
    ]);
    $projectRole = Roles::create([
        'code' => 'project_same_scope_'.$user->id,
        'name' => 'Project Member',
        'scope_type' => 'project',
        'is_system' => false,
    ]);

    $teamMembership = Membership::create([
        'user_id' => $user->id,
        'role_id' => $teamRole->id,
        'scope_id' => 1,
    ]);
    $projectMembership = Membership::create([
        'user_id' => $user->id,
        'role_id' => $projectRole->id,
        'scope_id' => 1,
    ]);

    $this->assertDatabaseHas('memberships', [
        'id' => $teamMembership->id,
        'user_id' => $user->id,
        'scope_type' => 'team',
        'scope_id' => 1,
    ]);
    $this->assertDatabaseHas('memberships', [
        'id' => $projectMembership->id,
        'user_id' => $user->id,
        'scope_type' => 'project',
        'scope_id' => 1,
    ]);
});

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

    $teamResourceType = ResourceTypes::firstOrCreate(['code' => 'team']);
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
    $resourceType = ResourceTypes::firstOrCreate(['code' => 'team']);
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
    $resourceType = ResourceTypes::firstOrCreate(['code' => 'team']);
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

    $resourceType = ResourceTypes::firstOrCreate(['code' => 'team_member']);
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

    $resourceType = ResourceTypes::firstOrCreate(['code' => 'team_member']);
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
    configureTeamProjectWorkflowVault();
    fakeProjectClamAvScan(calls: 3);
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
    $teamResourceType = ResourceTypes::firstOrCreate(['code' => 'team']);
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
        'resource_type_id' => ResourceTypes::firstOrCreate(['code' => 'project'])->id,
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
    expect($uploadedItem->encryption_key_ref)->toBe('teamate-files');
    expect($uploadedItem->encryption_key_version)->toBe('1');
    expect($uploadedItem->scan_status)->toBe('clean');
    expect($uploadedItem->malware_signature)->toBeNull();
    expect(Storage::disk('local')->get($uploadedItem->storage_ref))->toStartWith("TEAMATE-ENCRYPTED-FILE-1\n");
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
    $downloadResponse->assertOk()->assertDownload('kernel.rs');

    $encryptedFilesBeforeVaultFailure = Storage::disk('local')->allFiles("projects/{$project->id}");
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        'http://vault.test/v1/transit/datakey/plaintext/teamate-files' => Http::response([], 503),
    ]);

    $failedUpload = $this->actingAs($owner)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->create('vault-failure.txt', 10, 'text/plain'),
        'relative_path' => 'vault-failure/new-folder/vault-failure.txt',
    ]);

    $failedUpload->assertStatus(503)->assertJson([
        'message' => 'The file encryption service is unavailable. Please try again later.',
    ]);
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'vault-failure.txt',
    ]);
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'vault-failure',
    ]);
    expect(Storage::disk('local')->allFiles("projects/{$project->id}"))
        ->toBe($encryptedFilesBeforeVaultFailure);
});

test('team member with read permission on the team and project can view the project', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );

    $response = $this->actingAs($member)->get(route('projects.show', [$team, $project]));

    $response->assertOk();
});

test('project page hides items without read permission and refuses unreadable folders', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $readableFile = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::READ, 'readable.txt');
    $writeOnlyFile = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::WRITE, 'write-only.txt');
    $unreadableFile = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::OWN, 'unreadable.txt');
    $readableFolder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::READ, 'readable-folder');
    $unreadableFolder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::OWN, 'unreadable-folder');
    $readableFile->update(['mime_type' => 'text/plain']);

    $response = $this->actingAs($member)->get(route('projects.show', [$team, $project]));

    $response->assertOk()
        ->assertSee($readableFile->name)
        ->assertSee(route('projects.items.preview', [$team, $project, $readableFile]))
        ->assertSee(route('projects.items.download', [$team, $project, $readableFolder]))
        ->assertSee($writeOnlyFile->name)
        ->assertDontSee($unreadableFile->name)
        ->assertDontSee($unreadableFolder->name);
    $response->assertDontSee(route('projects.items.download', [$team, $project, $writeOnlyFile]));

    $this->actingAs($member)
        ->get(route('projects.show', [$team, $project]).'?folder='.$unreadableFolder->id)
        ->assertForbidden();
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

test('project member with folder write permission can upload into that folder without project write permission', function () {
    Storage::fake('local');
    configureTeamProjectWorkflowVault();
    fakeProjectClamAvScan();
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $folder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::WRITE, 'writable-folder');

    $response = $this->actingAs($member)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->create('allowed.txt', 10, 'text/plain'),
        'parent_id' => $folder->id,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    $uploadedFile = ProjectItem::query()
        ->where('project_id', $project->id)
        ->where('parent_id', $folder->id)
        ->where('name', 'allowed.txt')
        ->firstOrFail();
    expect($uploadedFile->isFile())->toBeTrue();
    Storage::disk('local')->assertExists($uploadedFile->storage_ref);
    expect($uploadedFile->encryption_key_ref)->toBe('teamate-files');
});

test('malware is stored only after the uploader confirms the unchanged file', function () {
    Storage::fake('local');
    configureTeamProjectWorkflowVault();
    fakeProjectClamAvScan('Win.Test.EICAR', 2);
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ | ResourceGrants::WRITE,
        ResourceGrants::READ | ResourceGrants::WRITE,
    );
    $fileContents = 'test malware fixture';
    $uploadUrl = route('projects.upload', [$team, $project]);

    $warningResponse = $this->actingAs($member)->postJson($uploadUrl, [
        'file' => UploadedFile::fake()->createWithContent('eicar.txt', $fileContents),
    ]);

    $warningResponse->assertStatus(422)->assertJson([
        'malware_detected' => true,
        'signature' => 'Win.Test.EICAR',
    ]);
    $confirmationToken = $warningResponse->json('confirmation_token');
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'eicar.txt',
    ]);
    Storage::disk('local')->assertDirectoryEmpty("projects/{$project->id}");

    $uploadResponse = $this->actingAs($member)->postJson($uploadUrl, [
        'file' => UploadedFile::fake()->createWithContent('eicar.txt', $fileContents),
        'malware_confirmation' => $confirmationToken,
    ]);

    $uploadResponse->assertOk()->assertJson(['success' => true]);
    $item = ProjectItem::query()
        ->where('project_id', $project->id)
        ->where('name', 'eicar.txt')
        ->firstOrFail();
    expect($item->scan_status)->toBe('malware');
    expect($item->malware_signature)->toBe('Win.Test.EICAR');
    expect($item->malware_acknowledged_by)->toBe($member->id);
    expect($item->malware_acknowledged_at)->not->toBeNull();
    Storage::disk('local')->assertExists($item->storage_ref);

    $this->actingAs($member)
        ->get(route('projects.show', [$team, $project]))
        ->assertOk()
        ->assertSee('Malware detected: Win.Test.EICAR');
});

test('upload returns 422 when malware confirmation is reused for different content', function () {
    Storage::fake('local');
    configureTeamProjectWorkflowVault();
    fakeProjectClamAvScan('Win.Test.EICAR', 2);
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::WRITE,
        ResourceGrants::WRITE,
    );

    $warningResponse = $this->actingAs($member)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->createWithContent('eicar.txt', 'first content'),
    ]);
    $confirmationToken = $warningResponse->json('confirmation_token');

    $uploadResponse = $this->actingAs($member)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->createWithContent('eicar.txt', 'changed content'),
        'malware_confirmation' => $confirmationToken,
    ]);

    $uploadResponse->assertStatus(422)->assertJson([
        'message' => 'The malware confirmation is invalid or expired. Please upload the file again.',
    ]);
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'eicar.txt',
    ]);
    Storage::disk('local')->assertDirectoryEmpty("projects/{$project->id}");
});

test('upload returns 503 without storing a file when ClamAV is unavailable', function () {
    Storage::fake('local');
    configureTeamProjectWorkflowVault();
    $scanner = Mockery::mock(ClamAvScanner::class);
    $scanner->shouldReceive('scan')
        ->once()
        ->andThrow(new ClamAvScanException('ClamAV is unavailable.'));
    app()->instance(ClamAvScanner::class, $scanner);
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::WRITE,
        ResourceGrants::WRITE,
    );

    $response = $this->actingAs($member)->postJson(route('projects.upload', [$team, $project]), [
        'file' => UploadedFile::fake()->create('unscanned.txt', 10, 'text/plain'),
    ]);

    $response->assertStatus(503)->assertJson([
        'message' => 'The virus scanning service is unavailable. The file was not uploaded. Please try again later.',
    ]);
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'unscanned.txt',
    ]);
    Storage::disk('local')->assertDirectoryEmpty("projects/{$project->id}");
});

test('project member without folder write permission cannot create a child folder', function () {
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::WRITE,
    );
    $folder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::READ, 'restricted');

    $response = $this->actingAs($member)->post(route('projects.folders.store', [$team, $project]), [
        'name' => 'new-child',
        'parent_id' => $folder->id,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('project_items', [
        'project_id' => $project->id,
        'name' => 'new-child',
    ]);
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

test('project member without file read permission cannot view or download a file', function () {
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

    $previewResponse = $this->actingAs($member)->get(
        route('projects.items.preview', [$team, $project, $file]),
    );
    $downloadResponse = $this->actingAs($member)->get(
        route('projects.items.download', [$team, $project, $file]),
    );

    $previewResponse->assertForbidden();
    $downloadResponse->assertForbidden();
});

test('project member with file read permission can download a file', function () {
    Storage::fake('local');
    configureTeamProjectWorkflowVault();
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $file = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::READ, 'readable.txt');
    $file->update([
        'storage_backend' => 'local',
        'storage_ref' => 'projects/'.$project->id.'/readable.txt',
    ]);
    storeEncryptedTeamProjectTestFile($file, 'file contents');

    $response = $this->actingAs($member)->get(
        route('projects.items.download', [$team, $project, $file]),
    );

    $response->assertOk();
    $response->assertDownload('readable.txt');
    expect($response->getFile()->getContent())->toBe('file contents');
});

test('project member with file read permission can preview a safe file inline', function () {
    Storage::fake('local');
    configureTeamProjectWorkflowVault();
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $file = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::READ, 'preview.txt');
    $file->update([
        'mime_type' => 'text/plain',
        'storage_backend' => 'local',
        'storage_ref' => 'projects/'.$project->id.'/preview.txt',
    ]);
    storeEncryptedTeamProjectTestFile($file, 'preview contents');

    $response = $this->actingAs($member)->get(
        route('projects.items.preview', [$team, $project, $file]),
    );

    $response->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=utf-8')
        ->assertHeader('x-content-type-options', 'nosniff');
    expect($response->getFile()->getContent())->toBe('preview contents');
});

test('project member cannot preview active file content inline', function () {
    Storage::fake('local');
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $file = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::READ, 'unsafe.html');
    $file->update([
        'mime_type' => 'text/html',
        'storage_backend' => 'local',
        'storage_ref' => 'projects/'.$project->id.'/unsafe.html',
    ]);
    Storage::disk('local')->put($file->storage_ref, '<script>alert(1)</script>');

    $response = $this->actingAs($member)->get(
        route('projects.items.preview', [$team, $project, $file]),
    );

    $response->assertStatus(415);
});

test('project member with folder read permission can download a zip of readable descendants', function () {
    Storage::fake('local');
    configureTeamProjectWorkflowVault();
    ['team' => $team, 'project' => $project, 'member' => $member] = createProjectReadFixture(
        ResourceGrants::READ,
        ResourceGrants::READ,
    );
    $folder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::READ, 'documents');
    $nestedFolder = createProjectItemWithGrant($project, $member, 'folder', ResourceGrants::READ, 'nested');
    $nestedFolder->update(['parent_id' => $folder->id]);
    $readableFile = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::READ, 'readable.txt');
    $readableFile->update([
        'parent_id' => $nestedFolder->id,
        'storage_backend' => 'local',
        'storage_ref' => 'projects/'.$project->id.'/readable.txt',
    ]);
    storeEncryptedTeamProjectTestFile($readableFile, 'readable contents');
    $unreadableFile = createProjectItemWithGrant($project, $member, 'file', ResourceGrants::WRITE, 'private.txt');
    $unreadableFile->update([
        'parent_id' => $folder->id,
        'storage_backend' => 'local',
        'storage_ref' => 'projects/'.$project->id.'/private.txt',
    ]);
    storeEncryptedTeamProjectTestFile($unreadableFile, 'private contents');

    $response = $this->actingAs($member)->get(
        route('projects.items.download', [$team, $project, $folder]),
    );

    $response->assertOk()->assertDownload('documents.zip');
    $archivePath = tempnam(sys_get_temp_dir(), 'teamate-test-archive-');

    if ($archivePath === false) {
        throw new RuntimeException('Unable to create a temporary archive for the test.');
    }

    $archiveContent = $response->streamedContent();
    expect(file_put_contents($archivePath, $archiveContent))->toBe(strlen($archiveContent));

    $archive = new ZipArchive;

    try {
        expect($archive->open($archivePath))->toBeTrue();
        expect($archive->getFromName('documents/nested/readable.txt'))->toBe('readable contents');
        expect($archive->locateName('documents/private.txt'))->toBeFalse();
    } finally {
        $archive->close();
        unlink($archivePath);
    }
});
