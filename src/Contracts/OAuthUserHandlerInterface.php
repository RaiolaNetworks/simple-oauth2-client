<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Contracts;

use Illuminate\Database\Eloquent\Model;
use League\OAuth2\Client\Token\AccessTokenInterface;

interface OAuthUserHandlerInterface
{
    /**
     * Handle user loged with OAuth provider.
     *
     * @param array<string,string> $data
     */
    public function handleUser(array $data, AccessTokenInterface $accessToken): Model;
}
