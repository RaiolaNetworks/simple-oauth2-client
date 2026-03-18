<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Raiolanetworks\OAuth\Services\OAuthService
 */
class OAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'oauth';
    }
}
