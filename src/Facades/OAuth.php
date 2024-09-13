<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Raiolanetworks\Oauth\Oauth
 */
class OAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Raiolanetworks\OAuth\OAuth::class;
    }
}
