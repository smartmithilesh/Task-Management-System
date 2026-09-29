<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\Integrations\IntegrationRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeliverIntegrationEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, mixed> $payload */
    public function __construct(public readonly string $integrationPublicId, public readonly string $event, public readonly array $payload) {}

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function handle(IntegrationRegistry $registry): void
    {
        $integration = Integration::query()->where('public_id', $this->integrationPublicId)
            ->where('status', 'configured')->first();
        if ($integration === null) {
            return;
        }
        $adapter = $registry->find($integration->provider);
        if ($adapter === null) {
            Log::notice('No adapter is registered for a configured integration.', [
                'integration_id' => $integration->public_id,
                'provider' => $integration->provider,
            ]);

            return;
        }

        $adapter->deliver($integration, $this->event, $this->payload);
    }
}
