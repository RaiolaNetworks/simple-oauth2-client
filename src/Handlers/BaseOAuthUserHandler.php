<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Handlers;

use Illuminate\Database\Eloquent\Model;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;

class BaseOAuthUserHandler implements OAuthUserHandlerInterface
{
    /**
     * Handle user logged with OAuth provider.
     *
     * @param array<string, mixed> $userData
     */
    public function handleUser(array $userData, AccessTokenInterface $accessToken): Model
    {
        /** @var class-string<Model> $model */
        $model = config('oauth.user_model_name');

        return $model::updateOrCreate(
            [
                'email' => $userData['email'],
            ],
            [
                'name' => $userData['name'],
            ]
        );
    }
}
