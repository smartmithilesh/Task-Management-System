<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class WebhookAdapter implements IntegrationAdapter
{
    public function key(): string
    {
        return 'webhook';
    }

    public function capabilities(): array
    {
        return ['outbound_events', 'signed_payloads', 'connection_test'];
    }

    public function validateCredentials(array $credentials): void
    {
        if (! isset($credentials['endpoint'], $credentials['signing_secret']) || $credentials['signing_secret'] === '') {
            throw new RuntimeException('Webhook endpoint and signing secret are required.');
        }
        $this->publicEndpointResolution($credentials['endpoint']);
    }

    public function testConnection(Integration $integration): array
    {
        $this->send($integration, 'integration.test', ['integration_id' => $integration->public_id]);

        return ['connected' => true, 'message' => 'The test event was accepted by the webhook endpoint.'];
    }

    /** @param array<string, mixed> $payload */
    public function deliver(Integration $integration, string $event, array $payload): void
    {
        $this->send($integration, $event, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function send(Integration $integration, string $event, array $payload): void
    {
        $integration->loadMissing('credentials');
        $credentials = $integration->credentials->keyBy('key');
        $endpoint = $credentials->get('endpoint')?->encrypted_value;
        $secret = $credentials->get('signing_secret')?->encrypted_value;
        if (! is_string($endpoint) || ! is_string($secret) || $secret === '') {
            throw new RuntimeException('Webhook endpoint and signing secret are required.');
        }
        $curlResolve = $this->publicEndpointResolution($endpoint);

        $timestamp = (string) now()->timestamp;
        $eventId = isset($payload['event_id']) && is_string($payload['event_id']) ? $payload['event_id'] : (string) Str::uuid();
        unset($payload['event_id']);
        $body = json_encode([
            'id' => $eventId,
            'event' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => [$curlResolve]]])
            ->withHeaders([
                'X-Taskflow-Event' => $event,
                'X-Taskflow-Event-ID' => $eventId,
                'X-Taskflow-Timestamp' => $timestamp,
                'X-Taskflow-Signature' => 'sha256='.$signature,
            ])
            ->withBody($body, 'application/json')
            ->post($endpoint);

        if (! $response->successful()) {
            Log::warning('Webhook delivery returned an unsuccessful status.', [
                'integration_id' => $integration->public_id,
                'event' => $event,
                'status' => $response->status(),
            ]);
            throw new RuntimeException('Webhook endpoint returned HTTP '.$response->status().'.');
        }
    }

    private function publicEndpointResolution(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Webhook endpoints must be public HTTPS URLs without embedded credentials.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new RuntimeException('Webhook endpoints cannot target local hostnames.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveAddresses($host);
        if ($addresses === []) {
            throw new RuntimeException('The webhook host did not resolve to a public IP address.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Webhook endpoints cannot resolve to private or reserved IP ranges.');
            }
        }

        $port = $parts['port'] ?? 443;
        $resolvedAddress = $addresses[0];
        $resolveHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $resolveAddress = str_contains($resolvedAddress, ':') ? '['.$resolvedAddress.']' : $resolvedAddress;

        return $resolveHost.':'.$port.':'.$resolveAddress;
    }

    /** @return list<string> */
    private function resolveAddresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }

        return collect($records)->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter(fn (?string $address): bool => $address !== null)
            ->unique()
            ->values()
            ->all();
    }
}
