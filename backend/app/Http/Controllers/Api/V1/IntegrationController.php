<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\Integrations\IntegrationRegistry;
use App\Services\Integrations\OAuthCapableAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class IntegrationController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request, IntegrationRegistry $registry): JsonResponse
    {
        $this->authorizePermission($request->user(), 'integrations.manage');
        $integrations = Integration::query()->where('organization_id', $this->requireOrganization($request->user()))
            ->with('connectedBy:id,name')->orderBy('provider')->get()
            ->map(fn (Integration $integration): array => [
                'id' => $integration->public_id,
                'provider' => $integration->provider,
                'connection_name' => $integration->connection_name,
                'status' => $integration->status,
                'connected_at' => $integration->connected_at,
                'connected_by' => $integration->connectedBy?->name,
                'configuration' => $integration->configuration,
                'credentials_configured' => $integration->credentials()->exists(),
            ]);

        return response()->json(['data' => $integrations, 'adapters' => $registry->catalog()]);
    }

    public function store(Request $request, IntegrationRegistry $registry): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'integrations.manage');
        $organizationId = $this->requireOrganization($actor);
        $validated = $request->validate([
            'provider' => ['required', Rule::in(collect($registry->catalog())->pluck('key')->all())],
            'connection_name' => ['nullable', 'string', 'max:100'],
            'configuration' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array', 'max:20'],
            'credentials.*' => ['required', 'string', 'max:5000'],
        ]);
        $this->assertSafeConfiguration($validated['configuration'] ?? []);
        if ($validated['provider'] === 'webhook') {
            validator($validated, [
                'credentials.endpoint' => ['required', 'string', 'url', 'max:2000'],
                'credentials.signing_secret' => ['required', 'string', 'min:24', 'max:5000'],
            ])->validate();
        }
        $adapter = $registry->find($validated['provider']);
        if ($adapter instanceof OAuthCapableAdapter) {
            throw ValidationException::withMessages(['provider' => ['Connect this provider through its authorization flow.']]);
        }
        try {
            $adapter?->validateCredentials($validated['credentials'] ?? []);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['credentials.endpoint' => [$exception->getMessage()]]);
        }
        foreach (array_keys($validated['credentials'] ?? []) as $credentialKey) {
            if (preg_match('/^[A-Za-z0-9_.-]{1,100}$/', (string) $credentialKey) !== 1) {
                throw ValidationException::withMessages(['credentials' => ['Credential names must use letters, numbers, dots, underscores, or hyphens.']]);
            }
        }

        $integration = DB::transaction(function () use ($validated, $organizationId, $actor): Integration {
            $integration = Integration::query()->updateOrCreate(
                ['organization_id' => $organizationId, 'provider' => $validated['provider'], 'connection_name' => $validated['connection_name'] ?? 'primary'],
                ['status' => 'configured', 'configuration' => $validated['configuration'] ?? [], 'connected_by' => $actor->id, 'connected_at' => now()],
            );
            foreach ($validated['credentials'] ?? [] as $key => $value) {
                IntegrationCredential::query()->updateOrCreate(
                    ['integration_id' => $integration->id, 'key' => $key],
                    ['encrypted_value' => $value, 'rotated_at' => now()],
                );
            }

            return $integration;
        });

        return response()->json(['data' => ['id' => $integration->public_id, 'provider' => $integration->provider, 'status' => $integration->status]], 201);
    }

    public function authorizeOAuth(Request $request, string $provider, IntegrationRegistry $registry): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'integrations.manage');
        $organizationId = $this->requireOrganization($actor);
        $adapter = $registry->findOAuth($provider);
        abort_if($adapter === null, 404, 'This provider is not enabled by the server administrator.');
        $validated = $request->validate([
            'connection_name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._ -]*$/'],
            'configuration' => ['nullable', 'array', 'max:10'],
            'configuration.channel_id' => [Rule::requiredIf($provider === 'slack'), 'nullable', 'string', 'max:100', 'regex:/^[CG][A-Z0-9]{6,}$/'],
        ]);
        $this->assertSafeConfiguration($validated['configuration'] ?? []);
        $connectionName = $validated['connection_name'] ?? 'primary';
        $existing = Integration::query()->where('organization_id', $organizationId)->where('provider', $provider)->where('connection_name', $connectionName)->first();
        $integration = Integration::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'provider' => $provider, 'connection_name' => $connectionName],
            ['status' => 'pending', 'configuration' => $validated['configuration'] ?? $existing?->configuration],
        );
        $state = bin2hex(random_bytes(32));
        Cache::put($this->oauthStateKey($state), [
            'provider' => $provider,
            'integration_id' => $integration->public_id,
            'organization_id' => $organizationId,
            'user_id' => $actor->id,
            'previous_status' => $existing?->status,
        ], now()->addMinutes(10));
        $redirectUri = $this->oauthRedirectUri($provider);

        return response()->json(['data' => [
            'authorization_url' => $adapter->authorizationUrl($state, $redirectUri),
            'expires_in' => 600,
        ]]);
    }

    public function oauthCallback(Request $request, string $provider, IntegrationRegistry $registry): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $stateData = $state !== '' ? Cache::pull($this->oauthStateKey($state)) : null;
        if (! is_array($stateData) || ($stateData['provider'] ?? null) !== $provider) {
            return $this->oauthReturn('invalid_state');
        }

        $integration = Integration::query()
            ->where('organization_id', $stateData['organization_id'] ?? 0)
            ->where('public_id', $stateData['integration_id'] ?? '')
            ->where('provider', $provider)
            ->first();
        $adapter = $registry->findOAuth($provider);
        if ($integration === null || $adapter === null) {
            if ($integration !== null) {
                $integration->update(['status' => $stateData['previous_status'] ?? 'disconnected']);
            }

            return $this->oauthReturn('unavailable');
        }
        $initiator = User::query()->whereKey($stateData['user_id'] ?? 0)
            ->where('organization_id', $stateData['organization_id'] ?? 0)->first();
        if ($initiator === null || ! $initiator->hasPermission('integrations.manage')) {
            $integration->update(['status' => $stateData['previous_status'] ?? 'disconnected']);

            return $this->oauthReturn('unauthorized');
        }
        if ($request->filled('error')) {
            $integration->update(['status' => $stateData['previous_status'] ?? 'disconnected']);

            return $this->oauthReturn('denied');
        }

        $validated = validator($request->query(), [
            'code' => ['required', 'string', 'max:4096'],
            'state' => ['required', 'string', 'size:64'],
        ])->validate();

        try {
            $redirectUri = $this->oauthRedirectUri($provider);
            $credentials = $adapter->exchangeAuthorizationCode($validated['code'], $redirectUri);
            $adapter->validateCredentials($credentials);
            DB::transaction(function () use ($integration, $credentials, $initiator): void {
                foreach ($credentials as $key => $value) {
                    $integration->credentials()->updateOrCreate(
                        ['key' => $key],
                        ['encrypted_value' => $value, 'rotated_at' => now()],
                    );
                }
                $integration->update([
                    'status' => 'configured',
                    'connected_by' => $initiator->id,
                    'connected_at' => now(),
                ]);
            });

            return $this->oauthReturn('connected');
        } catch (Throwable $exception) {
            report($exception);
            $integration->update(['status' => $stateData['previous_status'] ?? 'error']);

            return $this->oauthReturn('failed');
        }
    }

    public function test(Request $request, string $integration, IntegrationRegistry $registry): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'integrations.manage');
        $target = Integration::query()->where('organization_id', $this->requireOrganization($actor))
            ->where('public_id', $integration)->firstOrFail();
        $adapter = $registry->find($target->provider);
        abort_if($adapter === null, 422, 'No adapter is available for this provider.');
        try {
            return response()->json(['data' => $adapter->testConnection($target)]);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['integration' => [$exception->getMessage()]]);
        }
    }

    public function destroy(Request $request, string $integration, IntegrationRegistry $registry): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'integrations.manage');
        $target = Integration::query()->where('organization_id', $this->requireOrganization($actor))->where('public_id', $integration)->firstOrFail();
        $adapter = $registry->findOAuth($target->provider);
        $remoteRevoked = $target->provider === 'webhook';
        if ($adapter !== null) {
            try {
                $remoteRevoked = $adapter->revoke($target);
            } catch (Throwable $exception) {
                report($exception);
                $remoteRevoked = false;
            }
        }
        $target->update(['status' => 'disconnected', 'configuration' => null, 'connected_at' => null]);
        $target->credentials()->delete();

        return response()->json(['success' => true, 'data' => ['remote_token_revoked' => $remoteRevoked]]);
    }

    /** @param array<string, mixed> $configuration */
    private function assertSafeConfiguration(array $configuration): void
    {
        $sensitiveWords = '/(secret|token|password|credential|private.?key|authorization)/i';
        $containsSensitiveKey = function (array $values) use (&$containsSensitiveKey, $sensitiveWords): bool {
            foreach ($values as $key => $value) {
                if (preg_match($sensitiveWords, (string) $key) === 1 || (is_array($value) && $containsSensitiveKey($value))) {
                    return true;
                }
            }

            return false;
        };
        if ($containsSensitiveKey($configuration)) {
            throw ValidationException::withMessages([
                'configuration' => ['Store secrets in the credentials field so they are encrypted.'],
            ]);
        }
    }

    private function oauthStateKey(string $state): string
    {
        return 'integrations.oauth.state.'.hash('sha256', $state);
    }

    private function oauthReturn(string $status): RedirectResponse
    {
        return redirect()->to(rtrim((string) config('app.url'), '/').'/integrations/?oauth='.rawurlencode($status));
    }

    private function oauthRedirectUri(string $provider): string
    {
        return rtrim((string) config('app.url'), '/').route('api.v1.integrations.oauth.callback', ['provider' => $provider], false);
    }
}
