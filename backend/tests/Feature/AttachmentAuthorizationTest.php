<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attachment_download_requires_a_session_and_matching_organization(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $permission = Permission::factory()->create(['name' => 'tasks.view']);
        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $firstProject = Project::factory()->for($organization)->create(['created_by' => $user->id]);
        $otherUser = User::factory()->for($otherOrganization)->create();
        $otherProject = Project::factory()->for($otherOrganization)->create(['created_by' => $otherUser->id]);
        $firstStatus = TaskStatus::factory()->forOrganization($organization)->create();
        $otherStatus = TaskStatus::factory()->forOrganization($otherOrganization)->create();
        $firstTask = Task::factory()->for($firstProject)->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'status_id' => $firstStatus->id,
        ]);
        $otherTask = Task::factory()->for($otherProject)->create([
            'organization_id' => $otherOrganization->id,
            'created_by' => $otherUser->id,
            'status_id' => $otherStatus->id,
        ]);
        $visible = $firstTask->attachments()->create([
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'path' => 'task-attachments/visible.pdf',
            'original_name' => 'visible.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 5,
            'sha256' => hash('sha256', 'file1'),
        ]);
        $hidden = $otherTask->attachments()->create([
            'uploaded_by' => $otherUser->id,
            'disk' => 'local',
            'path' => 'task-attachments/hidden.pdf',
            'original_name' => 'hidden.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 5,
            'sha256' => hash('sha256', 'file2'),
        ]);
        Storage::disk('local')->put('task-attachments/visible.pdf', 'file1');
        Storage::disk('local')->put('task-attachments/hidden.pdf', 'file2');
        $plainToken = 'attachment-access-token';
        ApiToken::query()->create(['user_id' => $user->id, 'name' => 'Test', 'token_hash' => hash('sha256', $plainToken)]);

        $this->getJson('/api/v1/attachments/'.$visible->public_id.'/download')->assertUnauthorized();
        $this->withToken($plainToken)->get('/api/v1/attachments/'.$visible->public_id.'/download')->assertOk();
        $this->withToken($plainToken)->getJson('/api/v1/attachments/'.$hidden->public_id.'/download')->assertNotFound();
    }
}
