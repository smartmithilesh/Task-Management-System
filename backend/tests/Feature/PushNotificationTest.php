<?php

namespace Tests\Feature;

use App\Jobs\DeliverPushNotification;
use App\Models\ApiToken;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\Channels\PushNotificationChannel;
use App\Notifications\TaskAssignedNotification;
use App\Services\Notifications\FcmPushService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_device_tokens_are_encrypted_rotated_and_scoped_to_the_signed_in_user(): void
    {
        $this->enableFirebase();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $firstBearer = 'push-device-owner-one';
        $secondBearer = 'push-device-owner-two';
        ApiToken::query()->create(['user_id' => $firstUser->id, 'name' => 'Test', 'token_hash' => hash('sha256', $firstBearer)]);
        ApiToken::query()->create(['user_id' => $secondUser->id, 'name' => 'Test', 'token_hash' => hash('sha256', $secondBearer)]);
        $deviceId = 'a61b9d8a-79e5-4a0b-93de-31036fdfd7a1';

        $response = $this->withToken($firstBearer)->postJson('/api/v1/push-devices', [
            'device_id' => $deviceId, 'platform' => 'android', 'token' => 'fcm-device-token-value-0001',
        ])->assertCreated();
        $device = PushDevice::query()->where('user_id', $firstUser->id)->firstOrFail();
        $this->assertSame('fcm-device-token-value-0001', $device->encrypted_token);
        $this->assertNotSame('fcm-device-token-value-0001', $device->getRawOriginal('encrypted_token'));
        $this->assertSame(hash('sha256', 'fcm-device-token-value-0001'), $device->token_hash);
        $this->assertSame($device->public_id, $response->json('data.id'));

        $this->withToken($firstBearer)->postJson('/api/v1/push-devices', [
            'device_id' => $deviceId, 'platform' => 'ios', 'token' => 'fcm-device-token-value-0002',
        ])->assertCreated();
        $this->assertSame(1, $firstUser->pushDevices()->count());
        $this->assertSame('fcm-device-token-value-0002', $firstUser->pushDevices()->firstOrFail()->encrypted_token);

        $this->withToken($secondBearer)->postJson('/api/v1/push-devices', [
            'device_id' => $deviceId, 'platform' => 'android', 'token' => 'fcm-device-token-value-0002',
        ])->assertCreated();
        $this->assertSame(0, $firstUser->pushDevices()->count());
        $this->assertSame(1, $secondUser->pushDevices()->count());

        $this->withToken($firstBearer)->deleteJson('/api/v1/push-devices/'.$deviceId)->assertOk();
        $this->assertSame(1, $secondUser->pushDevices()->count());
        $this->withToken($secondBearer)->deleteJson('/api/v1/push-devices/'.$deviceId)->assertOk();
        $this->assertSame(0, $secondUser->pushDevices()->count());
    }

    public function test_firebase_http_v1_delivery_uses_a_signed_service_account_and_removes_unregistered_tokens(): void
    {
        $privateKeyPem = file_get_contents(base_path('tests/Fixtures/fcm-test-private-key.pem'));
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        $this->assertNotFalse($privateKey);
        $publicKey = openssl_pkey_get_details($privateKey)['key'];
        $this->configureFirebase($privateKeyPem);
        Cache::flush();
        $user = User::factory()->create();
        $device = PushDevice::query()->create([
            'user_id' => $user->id,
            'device_id' => 'a61b9d8a-79e5-4a0b-93de-31036fdfd7a1',
            'platform' => 'android',
            'token_hash' => hash('sha256', 'private-fcm-registration-token'),
            'encrypted_token' => 'private-fcm-registration-token',
            'last_seen_at' => now(),
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-fcm-access', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/v1/projects/taskflow-test/messages:send' => Http::response([
                'error' => ['details' => [['errorCode' => 'UNREGISTERED']]],
            ], 404),
        ]);

        app(FcmPushService::class)->deliver($device, [
            'title' => 'Task assigned',
            'body' => 'TSK-8: Review the release',
            'data' => ['event' => 'task.assigned', 'task_id' => 'task-public-id'],
        ]);

        $this->assertDatabaseMissing('push_devices', ['id' => $device->id]);
        Http::assertSent(function ($request) use ($publicKey): bool {
            if ($request->url() !== 'https://oauth2.googleapis.com/token') {
                return false;
            }
            $assertion = $request['assertion'];
            $parts = explode('.', $assertion);
            $signature = $this->base64UrlDecode($parts[2] ?? '');
            $claims = json_decode($this->base64UrlDecode($parts[1] ?? ''), true);

            return count($parts) === 3
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && is_array($claims)
                && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && openssl_verify($parts[0].'.'.$parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/taskflow-test/messages:send'
                && $request['message']['token'] === 'private-fcm-registration-token'
                && $request['message']['notification']['title'] === 'Task assigned'
                && $request['message']['data']['task_id'] === 'task-public-id';
        });
    }

    public function test_push_channel_queues_one_delivery_for_each_user_device(): void
    {
        $this->enableFirebase();
        $user = User::factory()->create();
        foreach (['device-one', 'device-two'] as $index => $label) {
            $user->pushDevices()->create([
                'device_id' => $index === 0 ? 'a61b9d8a-79e5-4a0b-93de-31036fdfd7a1' : 'b62c0e9b-80f6-4b1c-84ef-42147e0e8b2a',
                'platform' => 'android',
                'token_hash' => hash('sha256', $label),
                'encrypted_token' => $label,
            ]);
        }
        Queue::fake();

        app(FcmPushService::class)->queueForUser($user, ['title' => 'Task assigned', 'body' => 'TSK-1']);

        Queue::assertPushed(DeliverPushNotification::class, 2);
    }

    public function test_push_preference_is_only_offered_when_firebase_is_configured_and_a_device_is_registered(): void
    {
        $this->configureFirebase((string) file_get_contents(base_path('tests/Fixtures/fcm-test-private-key.pem')));
        $user = User::factory()->create();
        NotificationType::factory()->create(['slug' => 'task.assigned', 'default_enabled' => true]);
        $user->pushDevices()->create([
            'device_id' => 'a61b9d8a-79e5-4a0b-93de-31036fdfd7a1',
            'platform' => 'ios',
            'token_hash' => hash('sha256', 'device-token'),
            'encrypted_token' => 'device-token',
        ]);
        $notification = new TaskAssignedNotification([
            'id' => 'task-public-id', 'task_number' => 'TSK-1', 'title' => 'Review', 'project' => 'Web', 'assigned_by' => 'Alex',
        ]);

        $this->assertSame(['database', 'mail', PushNotificationChannel::class], $notification->via($user));
        $type = NotificationType::query()->where('slug', 'task.assigned')->firstOrFail();
        NotificationPreference::query()->create([
            'user_id' => $user->id, 'notification_type_id' => $type->id, 'channel' => 'push', 'enabled' => false,
        ]);
        $this->assertSame(['database', 'mail'], $notification->via($user));
    }

    private function enableFirebase(): void
    {
        $this->configureFirebase((string) file_get_contents(base_path('tests/Fixtures/fcm-test-private-key.pem')));
    }

    private function configureFirebase(string $privateKey): void
    {
        config([
            'services.firebase.project_id' => 'taskflow-test',
            'services.firebase.client_email' => 'taskflow-fcm@example.test',
            'services.firebase.private_key' => $privateKey,
        ]);
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value.str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/')) ?: '';
    }
}
