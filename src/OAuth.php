<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth;

class OAuth
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'oauth';
    }
}
