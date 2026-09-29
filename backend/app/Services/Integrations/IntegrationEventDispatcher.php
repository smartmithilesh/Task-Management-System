<?php

namespace App\Services\Integrations;

use App\Jobs\DeliverIntegrationEvent;
use App\Models\Integration;
use Illuminate\Support\Str;

class IntegrationEventDispatcher
{
    public function __construct(private readonly IntegrationRegistry $registry) {}

    /** @param array<string, mixed> $payload */
    public function dispatch(int $organizationId, string $event, array $payload): void
    {
        $payload['event_id'] = (string) Str::uuid();
        Integration::query()->where('organization_id', $organizationId)->where('status', 'configured')
            ->select(['id', 'public_id', 'provider'])->each(function (Integration $integration) use ($event, $payload): void {
                $adapter = $this->registry->find($integration->provider);
                if ($adapter !== null && in_array('event_delivery', $adapter->capabilities(), true)) {
                    DeliverIntegrationEvent::dispatch($integration->public_id, $event, $payload)->afterCommit();
                }
            });
    }
}
