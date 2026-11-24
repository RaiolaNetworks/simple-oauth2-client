<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Handlers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Models\OAuth;

/**
 * Resolves the local account of the person who logged in.
 *
 * The account is identified by the provider subject (`sub`), which never
 * changes, not by the email, which the person may be able to change. The email
 * is only used once, to link a subject seen for the first time to an existing
 * account, and only when the provider reports it as verified and that account
 * is not linked to another subject yet. Anything else would hand one person's
 * account to whoever claims their email.
 */
class BaseOAuthUserHandler implements OAuthUserHandlerInterface
{
    /**
     * Handle user logged with OAuth provider.
     *
     * @param array<string, mixed> $userData
     *
     * @throws IdentityProviderException when the account cannot be resolved safely
     */
    public function handleUser(array $userData, AccessTokenInterface $accessToken): Model
    {
        $subject  = is_string($userData['sub'] ?? null) ? trim($userData['sub']) : '';
        $email    = is_string($userData['email'] ?? null) ? mb_strtolower(trim($userData['email'])) : '';
        $verified = ($userData['email_verified'] ?? false) === true;

        if ($subject === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw $this->reject('The provider did not return a valid subject and email');
        }

        $user = $this->linkedTo($subject) ?? $this->linkableByEmail($email, $subject, $verified);

        if ($user === null) {
            $user = $this->newModel();
            $user->setAttribute('email', $email);
        } elseif ($verified && $user->getAttribute('email') !== $email && ! $this->emailTaken($email, $user)) {
            // A verified email follows the person. An unverified one never replaces it.
            $user->setAttribute('email', $email);
        }

        $user->setAttribute('name', $this->displayName($userData, $email));
        $user->save();

        return $user;
    }

    /**
     * Account already linked to this subject, if any.
     */
    protected function linkedTo(string $subject): ?Model
    {
        $userIds = OAuth::query()->where('oauth_id', $subject)->distinct()->pluck('user_id');

        if ($userIds->count() > 1) {
            // Left behind by email matching in earlier versions: two accounts claim
            // the same person, and there is no safe way to pick one.
            throw $this->reject('The provider subject is linked to more than one account');
        }

        return $userIds->isEmpty() ? null : $this->query()->whereKey($userIds->first())->first();
    }

    /**
     * Existing account to link a subject seen for the first time to, if any.
     *
     * Returns null when there is no account with that email, so a new one is
     * created. Throws when there is one but it cannot be linked safely, since
     * creating a second account with the same email is not an option either.
     */
    protected function linkableByEmail(string $email, string $subject, bool $verified): ?Model
    {
        $user = $this->byEmail($email);

        if ($user === null) {
            return null;
        }

        if (! $verified) {
            throw $this->reject('The provider did not verify the email of an existing account');
        }

        $linkedElsewhere = OAuth::query()
            ->where('user_id', $user->getKey())
            ->where('oauth_id', '!=', $subject)
            ->exists();

        if ($linkedElsewhere) {
            throw $this->reject('The account with this email is linked to another provider subject');
        }

        return $user;
    }

    protected function byEmail(string $email): ?Model
    {
        return $this->query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    protected function emailTaken(string $email, Model $except): bool
    {
        return $this->query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereKeyNot($except->getKey())
            ->exists();
    }

    /**
     * @param array<string, mixed> $userData
     */
    protected function displayName(array $userData, string $email): string
    {
        foreach (['name', 'preferred_username'] as $claim) {
            if (is_string($userData[$claim] ?? null) && trim($userData[$claim]) !== '') {
                return trim($userData[$claim]);
            }
        }

        return $email;
    }

    /**
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        return $this->newModel()->newQuery();
    }

    protected function newModel(): Model
    {
        /** @var class-string<Model> $model */
        $model = config('oauth.user_model_name');

        return new $model();
    }

    protected function reject(string $reason): IdentityProviderException
    {
        return new IdentityProviderException($reason, 0, $reason);
    }
}
