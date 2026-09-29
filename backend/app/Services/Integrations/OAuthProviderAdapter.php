<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OAuthProviderAdapter implements IntegrationAdapter, OAuthCapableAdapter
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(private readonly string $provider, private readonly array $configuration) {}

    public function key(): string
    {
        return $this->provider;
    }

    public function capabilities(): array
    {
        return array_values($this->configuration['capabilities'] ?? ['oauth2_authorization_code', 'connection_test']);
    }

    /** @param array<string, string> $credentials */
    public function validateCredentials(array $credentials): void
    {
        if (! isset($credentials['access_token']) || $credentials['access_token'] === '') {
            throw new RuntimeException('The provider did not return an access token.');
        }
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $this->assertEnabled();
        $parameters = [
            'client_id' => $this->configuration['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->configuration['scopes'] ?? []),
            'state' => $state,
        ];
        if (($this->configuration['authorization_parameters'] ?? []) !== []) {
            $parameters = array_merge($parameters, $this->configuration['authorization_parameters']);
        }

        return $this->httpsUrl('authorization_url').'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, string> */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): array
    {
        $this->assertEnabled();
        $response = Http::asForm()->acceptJson()->timeout(10)->connectTimeout(3)->withoutRedirecting()->post(
            $this->httpsUrl('token_url'),
            [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $this->configuration['client_id'],
                'client_secret' => $this->configuration['client_secret'],
            ],
        );
        if (! $response->successful()) {
            throw new RuntimeException('The provider authorization could not be completed.');
        }

        $payload = $response->json();
        if (! is_array($payload) || ! is_string($payload['access_token'] ?? null) || $payload['access_token'] === '') {
            throw new RuntimeException('The provider returned an invalid authorization response.');
        }

        return $this->credentialValues($payload);
    }

    public function testConnection(Integration $integration): array
    {
        $integration->loadMissing('credentials');
        $credentials = $integration->credentials->keyBy('key');
        $accessToken = $credentials->get('access_token')?->encrypted_value;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Reconnect this provider to continue.');
        }

        $expiresAt = $credentials->get('token_expires_at')?->encrypted_value;
        if (is_string($expiresAt) && strtotime($expiresAt) !== false && strtotime($expiresAt) <= now()->addMinute()->timestamp) {
            $this->refreshAccessToken($integration, $credentials);
            $integration->load('credentials');
            $credentials = $integration->credentials->keyBy('key');
            $accessToken = $credentials->get('access_token')?->encrypted_value;
        }

        $response = $this->testRequest($accessToken);
        if ($response->status() === 401 && $this->refreshAccessToken($integration, $credentials)) {
            $integration->load('credentials');
            $accessToken = $integration->credentials->firstWhere('key', 'access_token')?->encrypted_value;
            $response = $this->testRequest($accessToken);
        }
        if (! $response->successful() || $response->json('ok') === false) {
            throw new RuntimeException('The provider connection test failed with HTTP '.$response->status().'.');
        }

        return ['connected' => true, 'message' => 'The provider connection test succeeded.'];
    }

    public function revoke(Integration $integration): bool
    {
        $integration->loadMissing('credentials');
        $credentials = $integration->credentials->keyBy('key');
        $accessToken = $credentials->get('access_token')?->encrypted_value;
        $tokenToRevoke = $this->provider === 'google_calendar'
            ? ($credentials->get('refresh_token')?->encrypted_value ?? $accessToken)
            : $accessToken;
        if (! is_string($tokenToRevoke) || $tokenToRevoke === '') {
            return true;
        }
        $request = Http::acceptJson()->timeout(4)->connectTimeout(2)->withoutRedirecting();

        $response = match ($this->provider) {
            'slack' => $request->withToken($tokenToRevoke)->post('https://slack.com/api/auth.revoke'),
            'github' => $request->withBasicAuth((string) $this->configuration['client_id'], (string) $this->configuration['client_secret'])
                ->delete('https://api.github.com/applications/'.rawurlencode((string) $this->configuration['client_id']).'/grant', ['access_token' => $tokenToRevoke]),
            'google_calendar' => Http::asForm()->timeout(4)->connectTimeout(2)->withoutRedirecting()
                ->post('https://oauth2.googleapis.com/revoke', ['token' => $tokenToRevoke]),
            'dropbox' => $request->withToken($tokenToRevoke)->post('https://api.dropboxapi.com/2/auth/token/revoke'),
            default => null,
        };
        if ($response === null) {
            return false;
        }

        return $response->successful() && ($response->json('ok') !== false);
    }

    /** @param array<string, mixed> $payload */
    public function deliver(Integration $integration, string $event, array $payload): void
    {
        if (in_array($this->provider, ['google_calendar', 'microsoft_calendar'], true)) {
            $this->deliverCalendarEvent($integration, $event, $payload);

            return;
        }
        if ($this->provider !== 'slack') {
            throw new RuntimeException('This provider is configured for authorization and connection checks only.');
        }
        $channel = $integration->configuration['channel_id'] ?? null;
        if (! is_string($channel) || preg_match('/^[CG][A-Z0-9]{6,}$/', $channel) !== 1) {
            throw new RuntimeException('Configure a valid Slack channel ID before enabling task event delivery.');
        }
        $integration->loadMissing('credentials');
        $accessToken = $integration->credentials->firstWhere('key', 'access_token')?->encrypted_value;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Reconnect this Slack integration to continue.');
        }

        $credentials = $integration->credentials->keyBy('key');
        $accessToken = $credentials->get('access_token')?->encrypted_value;
        $expiresAt = $credentials->get('token_expires_at')?->encrypted_value;
        if (is_string($expiresAt) && strtotime($expiresAt) !== false && strtotime($expiresAt) <= now()->addMinute()->timestamp) {
            $this->refreshAccessToken($integration, $credentials);
            $integration->load('credentials');
            $credentials = $integration->credentials->keyBy('key');
            $accessToken = $credentials->get('access_token')?->encrypted_value;
        }
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Reconnect this Slack integration to continue.');
        }

        $taskNumber = is_string($payload['task_number'] ?? null) ? $payload['task_number'] : (string) ($payload['task_id'] ?? '');
        $message = 'Taskflow: '.$event.($taskNumber !== '' ? ' · '.$taskNumber : '');
        $send = fn (string $token): Response => Http::acceptJson()->asJson()->withUserAgent(config('app.name').'/integration')
            ->withToken($token)->timeout(10)->connectTimeout(3)->withoutRedirecting()->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channel,
                'text' => $message,
                'mrkdwn' => false,
                'unfurl_links' => false,
                'unfurl_media' => false,
            ]);
        $response = $send($accessToken);
        if (($response->status() === 401 || $response->json('error') === 'invalid_auth') && $this->refreshAccessToken($integration, $credentials)) {
            $integration->load('credentials');
            $newAccessToken = $integration->credentials->firstWhere('key', 'access_token')?->encrypted_value;
            if (is_string($newAccessToken) && $newAccessToken !== '') {
                $response = $send($newAccessToken);
            }
        }
        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException('Slack rejected the task event.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function deliverCalendarEvent(Integration $integration, string $event, array $payload): void
    {
        if (! in_array($event, ['task.created', 'task.updated', 'task.status_changed', 'task.archived'], true)) {
            return;
        }
        $taskId = $payload['task_id'] ?? null;
        if (! is_string($taskId) || $taskId === '') {
            throw new RuntimeException('The task event is missing its task ID.');
        }
        $integration->loadMissing('credentials');
        $credentials = $integration->credentials->keyBy('key');
        $token = $this->calendarAccessToken($integration, $credentials);
        $calendarId = $integration->configuration['calendar_id'] ?? 'primary';
        if (! is_string($calendarId) || $calendarId === '' || strlen($calendarId) > 255) {
            throw new RuntimeException('Configure a valid calendar ID.');
        }
        $record = DB::table('integration_external_records')->where('integration_id', $integration->id)
            ->where('record_type', 'task')->where('local_id', $taskId)->first();

        if ($event === 'task.archived' || ! is_string($payload['due_at'] ?? null)) {
            if ($record !== null) {
                $this->deleteCalendarEvent($token, $calendarId, $record->external_id);
                DB::table('integration_external_records')->where('id', $record->id)->delete();
            }

            return;
        }

        $due = strtotime($payload['due_at']);
        if ($due === false) {
            throw new RuntimeException('The task due date is invalid.');
        }
        $date = gmdate('Y-m-d', $due);
        $endDate = gmdate('Y-m-d', strtotime($date.' +1 day'));
        $taskNumber = is_string($payload['task_number'] ?? null) ? $payload['task_number'] : $taskId;
        $title = is_string($payload['title'] ?? null) ? $payload['title'] : 'Task';
        $description = trim($taskNumber.' · '.(string) ($payload['project'] ?? '')."\n".(string) ($payload['description'] ?? ''));

        if ($this->provider === 'google_calendar') {
            $externalId = 'a'.substr(hash('sha256', $taskId), 0, 32);
            $url = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($externalId);
            $body = ['id' => $externalId, 'summary' => $taskNumber.' · '.$title, 'description' => $description,
                'start' => ['date' => $date], 'end' => ['date' => $endDate]];
            $response = Http::acceptJson()->asJson()->withToken($token)->timeout(10)->connectTimeout(3)->withoutRedirecting()->patch($url, $body);
            if ($response->status() === 404) {
                $response = Http::acceptJson()->asJson()->withToken($token)->timeout(10)->connectTimeout(3)->withoutRedirecting()->post(
                    'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events', $body);
            }
            if (! $response->successful()) {
                throw new RuntimeException('Google Calendar rejected the task event (HTTP '.$response->status().').');
            }
            $externalId = (string) ($response->json('id') ?? $externalId);
        } else {
            $base = 'https://graph.microsoft.com/v1.0/me/'.($calendarId === 'primary' ? 'events' : 'calendars/'.rawurlencode($calendarId).'/events');
            $body = ['subject' => $taskNumber.' · '.$title, 'body' => ['contentType' => 'text', 'content' => $description],
                'isAllDay' => true, 'start' => ['dateTime' => $date.'T00:00:00', 'timeZone' => 'UTC'],
                'end' => ['dateTime' => $endDate.'T00:00:00', 'timeZone' => 'UTC']];
            if ($record === null) {
                $transactionId = substr(hash('sha256', $integration->public_id.':'.$taskId), 0, 32);
                $response = Http::acceptJson()->asJson()->withToken($token)->timeout(10)->connectTimeout(3)->withoutRedirecting()->post($base, $body + ['transactionId' => $transactionId]);
                if (! $response->successful() || ! is_string($response->json('id'))) {
                    throw new RuntimeException('Microsoft Calendar rejected the task event (HTTP '.$response->status().').');
                }
                $externalId = $response->json('id');
            } else {
                $externalId = $record->external_id;
                $response = Http::acceptJson()->asJson()->withToken($token)->timeout(10)->connectTimeout(3)->withoutRedirecting()->patch($base.'/'.rawurlencode($externalId), $body);
                if ($response->status() === 404) {
                    DB::table('integration_external_records')->where('id', $record->id)->delete();
                    $this->deliverCalendarEvent($integration, 'task.updated', $payload);

                    return;
                }
                if (! $response->successful()) {
                    throw new RuntimeException('Microsoft Calendar rejected the task event (HTTP '.$response->status().').');
                }
            }
        }

        DB::table('integration_external_records')->updateOrInsert(
            ['integration_id' => $integration->id, 'record_type' => 'task', 'local_id' => $taskId],
            ['external_id' => $externalId, 'updated_at' => now(), 'created_at' => $record?->created_at ?? now()],
        );
    }

    private function deleteCalendarEvent(string $token, string $calendarId, string $externalId): void
    {
        $url = $this->provider === 'google_calendar'
            ? 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($externalId)
            : 'https://graph.microsoft.com/v1.0/me/'.($calendarId === 'primary' ? 'events' : 'calendars/'.rawurlencode($calendarId).'/events').'/'.rawurlencode($externalId);
        $response = Http::acceptJson()->withToken($token)->timeout(10)->connectTimeout(3)->withoutRedirecting()->delete($url);
        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('The calendar provider rejected task event removal (HTTP '.$response->status().').');
        }
    }

    private function calendarAccessToken(Integration $integration, $credentials): string
    {
        $token = $credentials->get('access_token')?->encrypted_value;
        $expiry = $credentials->get('token_expires_at')?->encrypted_value;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Reconnect this calendar integration to continue.');
        }
        if (is_string($expiry) && strtotime($expiry) !== false && strtotime($expiry) <= now()->addMinute()->timestamp) {
            if (! $this->refreshAccessToken($integration, $credentials)) {
                throw new RuntimeException('Reconnect this calendar integration to continue.');
            }
            $integration->load('credentials');
            $token = $integration->credentials->firstWhere('key', 'access_token')?->encrypted_value;
        }

        return is_string($token) && $token !== '' ? $token : throw new RuntimeException('Reconnect this calendar integration to continue.');
    }

    /** @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function credentialValues(array $payload): array
    {
        $values = ['access_token' => $payload['access_token']];
        if (is_string($payload['refresh_token'] ?? null) && $payload['refresh_token'] !== '') {
            $values['refresh_token'] = $payload['refresh_token'];
        }
        if (is_numeric($payload['expires_in'] ?? null)) {
            $values['token_expires_at'] = now()->addSeconds(max(0, (int) $payload['expires_in']))->toIso8601String();
        }

        return $values;
    }

    private function refreshAccessToken(Integration $integration, $credentials): bool
    {
        $refreshToken = $credentials->get('refresh_token')?->encrypted_value;
        if (! is_string($refreshToken) || $refreshToken === '' || empty($this->configuration['client_secret'])) {
            return false;
        }

        $response = Http::asForm()->acceptJson()->timeout(10)->connectTimeout(3)->withoutRedirecting()->post(
            $this->httpsUrl('token_url'),
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->configuration['client_id'],
                'client_secret' => $this->configuration['client_secret'],
            ],
        );
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || ! is_string($payload['access_token'] ?? null)) {
            return false;
        }
        DB::transaction(function () use ($integration, $payload): void {
            foreach ($this->credentialValues($payload) as $key => $value) {
                $integration->credentials()->updateOrCreate(['key' => $key], ['encrypted_value' => $value, 'rotated_at' => now()]);
            }
            if (! isset($payload['refresh_token'])) {
                $integration->credentials()->where('key', 'refresh_token')->update(['rotated_at' => now()]);
            }
            if (! is_numeric($payload['expires_in'] ?? null)) {
                $integration->credentials()->where('key', 'token_expires_at')->delete();
            }
        });

        return true;
    }

    private function testRequest(string $accessToken): Response
    {
        $request = Http::acceptJson()->asJson()->withUserAgent(config('app.name').'/oauth-integration')->withToken($accessToken)->timeout(10)->connectTimeout(3)->withoutRedirecting();
        $method = strtoupper((string) ($this->configuration['test_method'] ?? 'GET'));

        return $method === 'POST'
            ? $request->post($this->httpsUrl('test_url'), [])
            : $request->get($this->httpsUrl('test_url'));
    }

    private function assertEnabled(): void
    {
        if (! is_string($this->configuration['client_id'] ?? null) || $this->configuration['client_id'] === ''
            || ! is_string($this->configuration['client_secret'] ?? null) || $this->configuration['client_secret'] === '') {
            throw new RuntimeException('This provider has not been configured by the server administrator.');
        }
    }

    private function httpsUrl(string $key): string
    {
        $url = $this->configuration[$key] ?? null;
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('The OAuth provider URL is not configured as HTTPS.');
        }

        return $url;
    }
}
