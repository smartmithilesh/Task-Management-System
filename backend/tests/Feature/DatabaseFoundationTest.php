<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_task_relations_persist_parent_assignees_watchers_reviewers_and_tags(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create();
        $assignee = User::factory()->for($organization)->create();
        $watcher = User::factory()->for($organization)->create();
        $reviewer = User::factory()->for($organization)->create();
        $project = Project::factory()->for($organization)->create([
            'manager_id' => $owner->id,
            'created_by' => $owner->id,
        ]);
        $status = TaskStatus::factory()->forOrganization($organization)->create();
        $priority = TaskPriority::factory()->forOrganization($organization)->create();
        $tag = Tag::factory()->for($organization)->create();

        $parent = Task::factory()->for($project)->create([
            'organization_id' => $organization->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id,
            'created_by' => $owner->id,
        ]);
        $subtask = Task::factory()->for($project)->create([
            'organization_id' => $organization->id,
            'parent_task_id' => $parent->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id,
            'created_by' => $owner->id,
        ]);

        $parent->assignees()->attach($assignee->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);
        $parent->watchers()->attach($watcher->id);
        $parent->reviewers()->attach($reviewer->id, ['assigned_by' => $owner->id]);
        $parent->tags()->attach($tag->id, ['tagged_by' => $owner->id]);

        $parent->refresh()->load(['subtasks', 'assignees', 'watchers', 'reviewers', 'tags', 'status', 'priority']);

        $this->assertSame($parent->id, $subtask->parent->id);
        $this->assertTrue($parent->subtasks->contains('id', $subtask->id));
        $this->assertTrue($parent->assignees->contains('id', $assignee->id));
        $this->assertTrue($parent->watchers->contains('id', $watcher->id));
        $this->assertTrue($parent->reviewers->contains('id', $reviewer->id));
        $this->assertTrue($parent->tags->contains('id', $tag->id));
        $this->assertSame($status->id, $parent->status->id);
        $this->assertSame($priority->id, $parent->priority->id);
        $this->assertSame($parent->public_id, $parent->getRouteKey());
    }

    public function test_integration_credentials_are_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $integration = Integration::factory()->create();
        $secret = 'private-integration-token';
        $credential = IntegrationCredential::factory()->for($integration)->create([
            'encrypted_value' => $secret,
        ]);
        $storedValue = DB::table('integration_credentials')->where('id', $credential->id)->value('encrypted_value');
        $reloadedCredential = $credential->fresh();

        $this->assertNotSame($secret, $storedValue);
        $this->assertSame($secret, $reloadedCredential->encrypted_value);
        $this->assertArrayNotHasKey('encrypted_value', $reloadedCredential->toArray());
    }

    public function test_default_reference_data_seeding_is_repeatable_and_creates_no_demo_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('task_statuses', 7);
        $this->assertDatabaseCount('task_priorities', 4);
        $this->assertDatabaseCount('permissions', 27);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('notification_types', 9);
        $this->assertDatabaseCount('system_settings', 9);
        $this->assertDatabaseCount('users', 0);

        $superAdmin = Role::query()->where('scope_key', 'global:super-admin')->firstOrFail();
        $this->assertCount(27, $superAdmin->permissions);
    }

    public function test_departments_can_reuse_a_slug_across_organizations(): void
    {
        $firstOrganization = Organization::factory()->create();
        $secondOrganization = Organization::factory()->create();

        $firstDepartment = $firstOrganization->departments()->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
        ]);
        $secondDepartment = $secondOrganization->departments()->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
        ]);

        $this->assertNotSame($firstDepartment->id, $secondDepartment->id);
        $this->assertSame('engineering', $secondDepartment->slug);
    }

    public function test_hard_deleting_a_project_removes_its_tasks(): void
    {
        $organization = Organization::factory()->create();
        $project = Project::factory()->for($organization)->create();
        $task = Task::factory()->for($project)->create([
            'organization_id' => $organization->id,
        ]);

        $project->forceDelete();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
