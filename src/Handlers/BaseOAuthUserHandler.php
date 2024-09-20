<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Handlers;

use Illuminate\Database\Eloquent\Model;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;

class BaseOAuthUserHandler implements OAuthUserHandlerInterface
{
    /**
     * Handle user loged with OAuth provider.
     *
     * @param array<mixed> $userData
     */
    public function handleUser(array $userData, AccessTokenInterface $accessToken): Model
    {
        /** @var Model $model */
        $model = config('oauth.user_model_name');

        return (new $model())::updateOrCreate(
            [
                'email'    => $userData['email'],
                'oauth_id' => $userData['sub'],
            ],
            [
                'name'                   => $userData['name'],
                'oauth_token'            => $accessToken->getToken(),
                'oauth_refresh_token'    => $accessToken->getRefreshToken(),
                'oauth_token_expires_at' => $accessToken->getExpires(),
            ]
        );
    }
}
