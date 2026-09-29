<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\IntegrationRegistry;
use App\Services\Integrations\OAuthProviderAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_oauth_state_is_one_use_and_access_tokens_are_encrypted_at_rest(): void
    {
        $this->enableGithub();
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $permission = Permission::factory()->create(['name' => 'integrations.manage']);
        $role = Role::factory()->for($organization)->create();
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $plainToken = 'oauth-flow-test-token';
        $this->withToken($plainToken);
        ApiToken::query()->create(['user_id' => $user->id, 'name' => 'Test', 'token_hash' => hash('sha256', $plainToken)]);

        $start = $this->postJson('/api/v1/integrations/oauth/github/authorize', ['connection_name' => 'engineering'])->assertOk();
        $authorizationUrl = $start->json('data.authorization_url');
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $parameters);
        $this->assertSame('oauth-client-id', $parameters['client_id']);
        $this->assertSame('https://app.example.test/api/v1/integrations/oauth/github/callback', $parameters['redirect_uri']);
        $this->assertSame(64, strlen($parameters['state']));

        Http::fake(['https://github.com/login/oauth/access_token' => Http::response([
            'access_token' => 'provider-access-secret',
            'refresh_token' => 'provider-refresh-secret',
            'expires_in' => 3600,
        ])]);
        $callback = $this->get('/api/v1/integrations/oauth/github/callback?'.http_build_query([
            'state' => $parameters['state'], 'code' => 'one-time-provider-code',
        ]));
        $callback->assertRedirect('https://app.example.test/integrations/?oauth=connected');

        $integration = Integration::query()->where('organization_id', $organization->id)->where('provider', 'github')->firstOrFail();
        $storedToken = $integration->credentials()->where('key', 'access_token')->firstOrFail();
        $this->assertSame('provider-access-secret', $storedToken->encrypted_value);
        $this->assertNotSame('provider-access-secret', $storedToken->getRawOriginal('encrypted_value'));
        $this->assertSame('configured', $integration->status);
        $this->assertSame('engineering', $integration->connection_name);

        $this->get('/api/v1/integrations/oauth/github/callback?'.http_build_query([
            'state' => $parameters['state'], 'code' => 'replayed-code',
        ]))->assertRedirect('https://app.example.test/integrations/?oauth=invalid_state');
        Http::assertSentCount(1);
    }

    public function test_oauth_denial_restores_an_existing_connection_state(): void
    {
        $this->enableGithub();
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $this->grantIntegrationPermission($user, $organization);
        $plainToken = 'oauth-denial-test-token';
        ApiToken::query()->create(['user_id' => $user->id, 'name' => 'Test', 'token_hash' => hash('sha256', $plainToken)]);
        $integration = Integration::factory()->for($organization)->create([
            'provider' => 'github', 'connection_name' => 'primary', 'status' => 'configured',
        ]);
        $integration->credentials()->create(['key' => 'access_token', 'encrypted_value' => 'existing-token']);

        $start = $this->withToken($plainToken)->postJson('/api/v1/integrations/oauth/github/authorize')->assertOk();
        parse_str((string) parse_url($start->json('data.authorization_url'), PHP_URL_QUERY), $parameters);
        $this->get('/api/v1/integrations/oauth/github/callback?'.http_build_query([
            'state' => $parameters['state'], 'error' => 'access_denied',
        ]))->assertRedirect('https://app.example.test/integrations/?oauth=denied');

        $this->assertSame('configured', $integration->fresh()->status);
        $this->assertSame('existing-token', $integration->credentials()->where('key', 'access_token')->firstOrFail()->encrypted_value);
    }

    public function test_callback_rechecks_permission_and_does_not_exchange_code_after_access_is_revoked(): void
    {
        $this->enableGithub();
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $this->grantIntegrationPermission($user, $organization);
        $plainToken = 'oauth-revoked-permission-test';
        ApiToken::query()->create(['user_id' => $user->id, 'name' => 'Test', 'token_hash' => hash('sha256', $plainToken)]);

        $start = $this->withToken($plainToken)->postJson('/api/v1/integrations/oauth/github/authorize')->assertOk();
        parse_str((string) parse_url($start->json('data.authorization_url'), PHP_URL_QUERY), $parameters);
        $user->roles()->detach();
        Http::fake();

        $this->get('/api/v1/integrations/oauth/github/callback?'.http_build_query([
            'state' => $parameters['state'], 'code' => 'unused-code',
        ]))->assertRedirect('https://app.example.test/integrations/?oauth=unauthorized');

        $this->assertDatabaseHas('integrations', ['provider' => 'github', 'status' => 'disconnected']);
        Http::assertNothingSent();
    }

    public function test_disconnect_revokes_slack_token_and_always_removes_local_credentials(): void
    {
        config([
            'app.url' => 'https://app.example.test',
            'services.integrations.oauth.slack.enabled' => true,
            'services.integrations.oauth.slack.client_id' => 'slack-client-id',
            'services.integrations.oauth.slack.client_secret' => 'slack-client-secret',
        ]);
        $this->app->forgetInstance(IntegrationRegistry::class);
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $this->grantIntegrationPermission($user, $organization);
        $plainToken = 'oauth-disconnect-test-token';
        ApiToken::query()->create(['user_id' => $user->id, 'name' => 'Test', 'token_hash' => hash('sha256', $plainToken)]);
        $integration = Integration::factory()->for($organization)->create(['provider' => 'slack', 'status' => 'configured']);
        $integration->credentials()->create(['key' => 'access_token', 'encrypted_value' => 'slack-token-to-revoke']);
        Http::fake(['https://slack.com/api/auth.revoke' => Http::response(['ok' => true])]);

        $response = $this->withToken($plainToken)->deleteJson('/api/v1/integrations/'.$integration->public_id)->assertOk();

        $response->assertJsonPath('data.remote_token_revoked', true);
        $this->assertSame('disconnected', $integration->fresh()->status);
        $this->assertSame(0, $integration->credentials()->count());
        Http::assertSent(fn ($request): bool => $request->url() === 'https://slack.com/api/auth.revoke'
            && $request->hasHeader('Authorization', 'Bearer slack-token-to-revoke'));
    }

    public function test_oauth_provider_connection_checks_refresh_expired_access_tokens(): void
    {
        Http::fake([
            'https://api.github.com/user' => Http::sequence()->push(['message' => 'Bad credentials'], 401)->push(['login' => 'taskflow'], 200),
            'https://github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'refreshed-token', 'expires_in' => 3600,
            ]),
        ]);
        $this->enableGithub();
        $configuration = config('services.integrations.oauth.github');
        $adapter = new OAuthProviderAdapter('github', array_merge($configuration, [
            'client_id' => 'client-id', 'client_secret' => 'client-secret',
        ]));
        $integration = Integration::factory()->create(['provider' => 'github', 'status' => 'configured']);
        $integration->credentials()->create(['key' => 'access_token', 'encrypted_value' => 'expired-token']);
        $integration->credentials()->create(['key' => 'refresh_token', 'encrypted_value' => 'refresh-secret']);

        $result = $adapter->testConnection($integration);

        $this->assertTrue($result['connected']);
        $this->assertSame('refreshed-token', $integration->credentials()->where('key', 'access_token')->firstOrFail()->encrypted_value);
        Http::assertSentCount(3);
    }

    public function test_oauth_is_disabled_without_server_credentials(): void
    {
        config(['services.integrations.oauth.github.enabled' => false]);
        $adapter = new OAuthProviderAdapter('github', config('services.integrations.oauth.github'));
        $this->expectException(\RuntimeException::class);
        $adapter->authorizationUrl('random-state', 'https://app.example.test/callback');
    }

    public function test_oauth_tokens_cannot_be_added_through_the_generic_connection_endpoint(): void
    {
        $this->enableGithub();
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $this->grantIntegrationPermission($user, $organization);
        $plainToken = 'oauth-manual-credential-test';
        ApiToken::query()->create(['user_id' => $user->id, 'name' => 'Test', 'token_hash' => hash('sha256', $plainToken)]);

        $this->withToken($plainToken)->postJson('/api/v1/integrations', [
            'provider' => 'github',
            'credentials' => ['access_token' => 'injected-token'],
        ])->assertUnprocessable()->assertJsonValidationErrors('provider');
    }

    public function test_slack_adapter_delivers_a_minimal_plain_text_task_event(): void
    {
        Http::fake(['https://slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '123.456'])]);
        $configuration = array_merge(config('services.integrations.oauth.slack'), [
            'client_id' => 'slack-client-id', 'client_secret' => 'slack-client-secret',
        ]);
        $adapter = new OAuthProviderAdapter('slack', $configuration);
        $integration = Integration::factory()->create([
            'provider' => 'slack', 'status' => 'configured', 'configuration' => ['channel_id' => 'C0123456789'],
        ]);
        $integration->credentials()->create(['key' => 'access_token', 'encrypted_value' => 'slack-bot-token']);

        $adapter->deliver($integration, 'task.created', ['task_number' => 'TSK-10', 'title' => 'Private task title']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'C0123456789'
            && $request['mrkdwn'] === false
            && $request['text'] === 'Taskflow: task.created · TSK-10'
            && ! str_contains($request->body(), 'Private task title'));
    }

    private function enableGithub(): void
    {
        config([
            'app.url' => 'https://app.example.test',
            'services.integrations.oauth.github.enabled' => true,
            'services.integrations.oauth.github.enabled' => true,
            'services.integrations.oauth.github.client_id' => 'oauth-client-id',
            'services.integrations.oauth.github.client_secret' => 'oauth-client-secret',
        ]);
        $this->app->forgetInstance(IntegrationRegistry::class);
    }

    private function grantIntegrationPermission(User $user, Organization $organization): void
    {
        $permission = Permission::factory()->create(['name' => 'integrations.manage']);
        $role = Role::factory()->for($organization)->create();
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
    }
}
