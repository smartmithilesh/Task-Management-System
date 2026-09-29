<?php

namespace App\Services\Integrations;

use App\Models\Integration;

interface OAuthCapableAdapter
{
    public function authorizationUrl(string $state, string $redirectUri): string;

    /** @return array<string, string> */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): array;

    public function revoke(Integration $integration): bool;
}
