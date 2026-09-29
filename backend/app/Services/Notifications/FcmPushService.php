<?php

namespace App\Services\Notifications;

use App\Jobs\DeliverPushNotification;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmPushService
{
    private ?bool $configured = null;

    public function isConfigured(): bool
    {
        if ($this->configured !== null) {
            return $this->configured;
        }
        $email = config('services.firebase.client_email');
        $privateKey = str_replace('\\n', "\n", (string) config('services.firebase.private_key'));
        if ($this->projectId() === null || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $privateKey === '') {
            return $this->configured = false;
        }

        return $this->configured = openssl_pkey_get_private($privateKey) !== false;
    }

    /** @param array{title: string, body: string, data?: array<string, scalar|null>} $payload */
    public function queueForUser(User $user, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }
        $user->pushDevices()->select(['id', 'public_id'])->each(function (PushDevice $device) use ($payload): void {
            DeliverPushNotification::dispatch($device->public_id, $payload)->afterCommit();
        });
    }

    /** @param array{title: string, body: string, data?: array<string, scalar|null>} $payload */
    public function deliver(PushDevice $device, array $payload): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Push delivery is not configured.');
        }
        $device->refresh();
        $firebaseToken = $device->encrypted_token;
        if (! is_string($firebaseToken) || $firebaseToken === '') {
            $device->delete();

            return;
        }

        $message = [
            'message' => [
                'token' => $firebaseToken,
                'notification' => [
                    'title' => mb_substr($payload['title'], 0, 120),
                    'body' => mb_substr($payload['body'], 0, 500),
                ],
                'data' => collect($payload['data'] ?? [])->filter(fn ($value): bool => is_scalar($value))
                    ->map(fn ($value): string => (string) $value)->all(),
            ],
        ];
        $url = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($this->projectId()).'/messages:send';
        $accessToken = $this->accessToken();
        $response = Http::acceptJson()->asJson()->withToken($accessToken)->timeout(10)->connectTimeout(3)->withoutRedirecting()->post($url, $message);
        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
            $response = Http::acceptJson()->asJson()->withToken($this->accessToken())->timeout(10)->connectTimeout(3)->withoutRedirecting()->post($url, $message);
        }
        if (! $response->successful()) {
            if ($this->isUnregisteredToken($response->json())) {
                $device->delete();

                return;
            }
            throw new RuntimeException('Firebase Cloud Messaging rejected a push delivery with HTTP '.$response->status().'.');
        }
        $device->forceFill(['last_seen_at' => now()])->save();
    }

    private function accessToken(): string
    {
        $key = $this->cacheKey();
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $privateKey = str_replace('\\n', "\n", (string) config('services.firebase.private_key'));
        $signingKey = openssl_pkey_get_private($privateKey);
        if ($signingKey === false) {
            throw new RuntimeException('The Firebase service account key is invalid.');
        }
        $issuedAt = time();
        $assertion = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)).'.'.$this->base64UrlEncode(json_encode([
            'iss' => config('services.firebase.client_email'),
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_THROW_ON_ERROR));
        if (! openssl_sign($assertion, $signature, $signingKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The Firebase service account assertion could not be signed.');
        }
        $response = Http::asForm()->acceptJson()->timeout(10)->connectTimeout(3)->withoutRedirecting()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion.'.'.$this->base64UrlEncode($signature),
        ]);
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Firebase service account authentication failed.');
        }
        Cache::put($key, $token, now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 60)));

        return $token;
    }

    private function isUnregisteredToken(mixed $error): bool
    {
        $details = is_array($error) ? data_get($error, 'error.details', []) : [];

        return is_array($details) && collect($details)->contains(fn (mixed $detail): bool => is_array($detail)
            && ($detail['errorCode'] ?? null) === 'UNREGISTERED');
    }

    private function projectId(): ?string
    {
        $projectId = config('services.firebase.project_id');

        return is_string($projectId) && preg_match('/^[a-z0-9][a-z0-9-]{4,62}[a-z0-9]$/', $projectId) === 1 ? $projectId : null;
    }

    private function cacheKey(): string
    {
        return 'firebase.messaging.access_token.'.hash('sha256', (string) config('services.firebase.client_email').'|'.(string) config('services.firebase.project_id'));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
