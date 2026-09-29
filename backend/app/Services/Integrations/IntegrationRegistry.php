<?php

namespace App\Services\Integrations;

class IntegrationRegistry
{
    /** @param iterable<IntegrationAdapter> $adapters */
    public function __construct(private readonly iterable $adapters) {}

    public function find(string $key): ?IntegrationAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->key() === $key) {
                return $adapter;
            }
        }

        return null;
    }

    public function findOAuth(string $key): ?OAuthCapableAdapter
    {
        $adapter = $this->find($key);

        return $adapter instanceof OAuthCapableAdapter ? $adapter : null;
    }

    /** @return list<array{key: string, capabilities: list<string>}> */
    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->adapters as $adapter) {
            $catalog[] = ['key' => $adapter->key(), 'capabilities' => $adapter->capabilities()];
        }

        return $catalog;
    }
}
