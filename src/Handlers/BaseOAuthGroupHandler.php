<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Handlers;

use Illuminate\Database\Eloquent\Model;
use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;

class BaseOAuthGroupHandler implements OAuthGroupHandlerInterface
{
    /**
     * Handle groups retrieved from OAuth provider.
     *
     * @param array<string,string> $groups
     */
    public function handleGroups(array $groups, Model $user): void
    {
        // Add custom logic to management groups, for example, Spatie permissions logic.
    }
}
