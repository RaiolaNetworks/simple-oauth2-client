<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OAuthGroupHandlerInterface
{
    /**
     * Handle groups retrieved from OAuth provider.
     *
     * @param array<string,string> $groups
     */
    public function handleGroups(array $groups, Model $user): void;
}
