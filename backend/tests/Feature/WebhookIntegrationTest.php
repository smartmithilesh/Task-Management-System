<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Services\Integrations\WebhookAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WebhookIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_webhook_events_are_sent_with_an_hmac_signature(): void
    {
        Http::fake(['https://8.8.8.8/*' => Http::response([], 202)]);
        $integration = Integration::factory()->create(['provider' => 'webhook', 'status' => 'configured']);
        $integration->credentials()->create(['key' => 'endpoint', 'encrypted_value' => 'https://8.8.8.8/events']);
        $integration->credentials()->create(['key' => 'signing_secret', 'encrypted_value' => 'a-strong-test-signing-secret-value']);

        app(WebhookAdapter::class)->deliver($integration, 'task.created', ['task_number' => 'TSK-1']);

        Http::assertSent(function (Request $request): bool {
            $timestamp = $request->header('X-Taskflow-Timestamp')[0] ?? '';
            $signature = $request->header('X-Taskflow-Signature')[0] ?? '';
            $body = $request->body();

            return $request->url() === 'https://8.8.8.8/events'
                && $request->header('X-Taskflow-Event')[0] === 'task.created'
                && hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'a-strong-test-signing-secret-value'), $signature)
                && str_contains($body, 'TSK-1');
        });
    }

    public function test_webhook_rejects_private_and_non_https_destinations(): void
    {
        $integration = Integration::factory()->create(['provider' => 'webhook', 'status' => 'configured']);
        $integration->credentials()->create(['key' => 'endpoint', 'encrypted_value' => 'http://127.0.0.1/internal']);
        $integration->credentials()->create(['key' => 'signing_secret', 'encrypted_value' => 'a-strong-test-signing-secret-value']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public HTTPS');
        app(WebhookAdapter::class)->deliver($integration, 'task.created', []);
    }
}
