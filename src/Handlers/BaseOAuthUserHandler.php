<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Handlers;

use Illuminate\Database\Eloquent\Model;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Models\OAuth;

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
                'email' => $userData['email'],
            ],
            [
                'name' => $userData['name'],
            ]
        );
    }
}
