<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Handlers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;

class BaseOAuthUserHandler implements OAuthUserHandlerInterface
{
    /**
     * Handle user logged with OAuth provider.
     *
     * @param array<string, mixed> $userData
     *
     * @throws IdentityProviderException When the provider returns no usable email address.
     */
    public function handleUser(array $userData, AccessTokenInterface $accessToken): Model
    {
        /** @var class-string<Model> $model */
        $model = config('oauth.user_model_name');

        $email = $userData['email'] ?? null;

        // Without an email there is nothing to match the local account on. Raise
        // the exception the controller already catches, so the user is sent back
        // to the login route instead of meeting an uncaught error.
        if (! is_string($email) || $email === '') {
            throw new IdentityProviderException(
                'The OAuth provider returned no email address for this user',
                0,
                'Missing email claim'
            );
        }

        $name = $userData['name'] ?? null;

        /** @var Model $user */
        $user = $model::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $this->setUnusablePassword($user);
        }

        $user->fill(['name' => is_string($name) && $name !== '' ? $name : $email])->save();

        return $user;
    }

    /**
     * Give a newly created user a random password nobody can authenticate with.
     *
     * An OAuth user never signs in with a password, but Laravel's default users
     * table declares the column NOT NULL, so leaving it unset breaks the very
     * first login.
     *
     * This runs on creation only, and deliberately so: writing a password on
     * every login would leave every account in the application sharing whatever
     * value this method produces, which turns the login form into a skeleton key.
     * Projects that dropped the column are left untouched.
     */
    protected function setUnusablePassword(Model $user): void
    {
        if (! Schema::connection($user->getConnectionName())->hasColumn($user->getTable(), 'password')) {
            return;
        }

        $user->setAttribute('password', Hash::make(Str::random(64)));
    }
}
