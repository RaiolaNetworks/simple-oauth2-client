<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Services;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Raiolanetworks\OAuth\Events\OAuthTokenUpdated;
use Raiolanetworks\OAuth\Models\OAuth;

/**
 * Handles OAuth access-token renewal.
 *
 * Kept out of the controller so it can be reused from middleware without
 * resolving a full controller instance per request.
 */
class TokenRenewalService
{
    /**
     * Session key holding the cached token expiry timestamp. Lets the
     * middleware skip the database lookup while the token is still valid.
     */
    public const EXPIRY_SESSION_KEY = 'oauth2-token-expires-at';

    public function __construct(
        protected OAuthService $provider,
    ) {}

    /**
     * Renew the current user's access token when it has expired.
     *
     * Returns a redirect response when the session can no longer be renewed
     * (and the user was logged out), or null when nothing needed to happen.
     */
    public function renew(): ?RedirectResponse
    {
        /** @var string $guardName */
        $guardName = config('oauth.guard_name');

        if (! Auth::guard($guardName)->check()) {
            return null;
        }

        // Fast path: while the cached expiry says the token is still valid we
        // avoid touching the database entirely.
        $cachedExpiry = Session::get(self::EXPIRY_SESSION_KEY);

        if (is_int($cachedExpiry) && $cachedExpiry >= now()->timestamp) {
            return null;
        }

        $user      = Auth::guard($guardName)->user();
        $oauthData = OAuth::whereUserId($user?->getAuthIdentifier())->first();

        // @phpstan-ignore-next-line
        if ($oauthData === null || $oauthData->oauth_token === null || $oauthData->oauth_token_expires_at >= now()->timestamp) {
            // Nothing to renew: refresh the cache so future requests short-circuit.
            $this->rememberExpiry($oauthData?->oauth_token_expires_at); // @phpstan-ignore-line

            return null;
        }

        if (config('oauth.offline_access') === false) {
            return $this->unauthorizeAndLogout($oauthData, $guardName);
        }

        try {
            /** @var AccessToken $accessToken */
            $accessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $oauthData->oauth_refresh_token, // @phpstan-ignore-line
            ]);

            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $callback      = $resourceOwner->toArray();
        } catch (IdentityProviderException|ClientException) {
            return $this->unauthorizeAndLogout($oauthData, $guardName);
        }

        $oauthData->update([
            'oauth_token'            => $accessToken->getToken(),
            'oauth_refresh_token'    => $accessToken->getRefreshToken(),
            'oauth_token_expires_at' => $accessToken->getExpires(),
        ]);

        $this->rememberExpiry($accessToken->getExpires());

        /** @var Model $user */
        OAuthTokenUpdated::dispatch($user, $oauthData, $callback['groups'] ?? []);

        return null;
    }

    /**
     * Cache the token expiry timestamp in the session so the middleware can
     * skip the database lookup on subsequent requests.
     */
    public function rememberExpiry(?int $expiresAt): void
    {
        if ($expiresAt === null) {
            Session::forget(self::EXPIRY_SESSION_KEY);

            return;
        }

        Session::put(self::EXPIRY_SESSION_KEY, $expiresAt);
    }

    protected function unauthorizeAndLogout(OAuth $oauthData, string $guardName): RedirectResponse
    {
        $oauthData->update([
            'oauth_token'            => null,
            'oauth_refresh_token'    => null,
            'oauth_token_expires_at' => null,
        ]);

        Session::forget(self::EXPIRY_SESSION_KEY);

        Auth::guard($guardName)->logout();

        /** @var string $loginRouteName */
        $loginRouteName = config('oauth.login_route_name');

        return Redirect::route($loginRouteName)
            ->with(['message' => __('oauth::translations.session-expired')]);
    }
}
