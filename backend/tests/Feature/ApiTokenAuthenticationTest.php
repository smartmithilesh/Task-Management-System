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
use Tests\TestCase;

class ApiTokenAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_valid_bearer_token_authenticates_api_requests_and_logout_revokes_it(): void
    {
        $user = User::factory()->create();
        $plainTextToken = 'test-mobile-access-token';
        $token = ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'Test device',
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken($plainTextToken)->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.id', $user->public_id);
        $this->withToken($plainTextToken)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseMissing('api_tokens', ['id' => $token->id]);
        $this->withToken($plainTextToken)->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_an_expired_bearer_token_is_rejected(): void
    {
        $user = User::factory()->create();
        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'Expired device',
            'token_hash' => hash('sha256', 'expired-mobile-token'),
            'expires_at' => now()->subSecond(),
        ]);

        $this->withToken('expired-mobile-token')->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_mobile_login_issues_a_plaintext_token_once_for_the_device(): void
    {
        $user = User::factory()->create(['email' => 'mobile@example.test']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Android phone',
        ])->assertOk()->assertJsonPath('data.user.id', $user->public_id);

        $plainTextToken = $response->json('data.token');
        $this->assertIsString($plainTextToken);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'name' => 'Android phone',
            'token_hash' => hash('sha256', $plainTextToken),
        ]);
    }

    public function test_task_lists_are_scoped_to_the_authenticated_users_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $otherUser = User::factory()->for($otherOrganization)->create();
        $permission = Permission::factory()->create(['name' => 'tasks.view']);
        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $visibleProject = Project::factory()->for($organization)->create(['created_by' => $user->id]);
        $hiddenProject = Project::factory()->for($otherOrganization)->create(['created_by' => $otherUser->id]);
        $visibleStatus = TaskStatus::factory()->forOrganization($organization)->create();
        $hiddenStatus = TaskStatus::factory()->forOrganization($otherOrganization)->create();
        $visibleTask = Task::factory()->for($visibleProject)->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'status_id' => $visibleStatus->id,
        ]);
        Task::factory()->for($hiddenProject)->create([
            'organization_id' => $otherOrganization->id,
            'created_by' => $otherUser->id,
            'status_id' => $hiddenStatus->id,
        ]);
        $token = 'organization-scoped-token';
        ApiToken::query()->create(['user_id' => $user->id, 'name' => 'Test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/tasks')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleTask->public_id);
    }
}
