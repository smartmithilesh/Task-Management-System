<?php

namespace App\Services\Integrations;

use App\Models\Integration;

interface IntegrationAdapter
{
    public function key(): string;

    /** @return list<string> */
    public function capabilities(): array;

    /** @param array<string, string> $credentials */
    public function validateCredentials(array $credentials): void;

    /** @return array{connected: bool, message: string} */
    public function testConnection(Integration $integration): array;

    /** @param array<string, mixed> $payload */
    public function deliver(Integration $integration, string $event, array $payload): void;
}
