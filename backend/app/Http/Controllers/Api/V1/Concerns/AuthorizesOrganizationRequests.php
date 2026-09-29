<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;

trait AuthorizesOrganizationRequests
{
    protected function authorizePermission(User $user, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403);
    }

    protected function requireOrganization(User $user): int
    {
        abort_unless($user->organization_id !== null, 409, 'Set up your organization before managing its resources.');

        return $user->organization_id;
    }
}
