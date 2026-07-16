<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Controllers;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Events\OAuthTokenUpdated;
use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Services\OAuthService;
use Raiolanetworks\OAuth\Services\TokenRenewalService;
use Symfony\Component\HttpFoundation\RedirectResponse as HttpFoundationRedirectResponse;

class OAuthController extends Controller
{
    public function __construct(
        protected OAuthService $provider,
        protected OAuthUserHandlerInterface $userHandler,
        protected OAuthGroupHandlerInterface $groupHandler,
    ) {}

    public function request(): RedirectResponse|HttpFoundationRedirectResponse|Redirector
    {
        /** @var string $guardName */
        $guardName = config('oauth.guard_name');

        if (Auth::guard($guardName)->check()) {
            return Redirect::to('/');
        }

        $authUrl = $this->provider->getAuthorizationUrl([
            'prompt' => 'consent',
        ]);

        Session::put([
            'oauth2-state'    => $this->provider->getState(),
            'oauth2-pkceCode' => $this->provider->getPkceCode(),
        ]);

        return Redirect::away($authUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        /** @var string $guardName */
        $guardName = config('oauth.guard_name');

        if (Auth::guard($guardName)->check()) {
            return Redirect::intended();
        }

        try {
            $code         = $request->query('code');
            $state        = $request->query('state');
            $sessionState = Session::get('oauth2-state');
            $pkceCode     = Session::get('oauth2-pkceCode');

            if (! isset($code)) {
                throw new IdentityProviderException('Invalid code', 0, 'Invalid code');
            }

            if (! isset($state) || $sessionState === null || $state !== $sessionState) {
                throw new IdentityProviderException('Invalid state', 0, 'Invalid state');
            }

            if (! is_string($pkceCode)) {
                throw new IdentityProviderException('Invalid PKCE code', 0, 'Invalid PKCE code');
            }

            $this->provider->setPkceCode($pkceCode);

            /** @var AccessToken $accessToken */
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $callback = $this->provider->getResourceOwner($accessToken)->toArray();

            if (! isset($callback['sub'])) {
                throw new IdentityProviderException('Missing resource owner identifier', 0, 'Missing resource owner identifier');
            }

            $user = $this->userHandler->handleUser($callback, $accessToken);
            $this->groupHandler->handleGroups($callback['groups'] ?? [], $user);

            $oauthData = OAuth::updateOrCreate(
                [
                    'user_id'  => $user->getKey(),
                    'oauth_id' => $callback['sub'],
                ],
                [
                    'oauth_token'            => $accessToken->getToken(),
                    'oauth_refresh_token'    => $accessToken->getRefreshToken(),
                    'oauth_token_expires_at' => $accessToken->getExpires(),
                ]
            );

            OAuthTokenUpdated::dispatch($user, $oauthData, $callback['groups'] ?? []);
            app(TokenRenewalService::class)->rememberExpiry($accessToken->getExpires());
            Session::remove('oauth2-state');
            Session::remove('oauth2-pkceCode');

            Session::regenerate();

            /** @var Authenticatable&Model $user */
            Auth::guard($guardName)->login($user);

            /** @var string $redirectRouteCallbackOk */
            $redirectRouteCallbackOk = config('oauth.redirect_route_name_callback_ok');

            return redirect()->route($redirectRouteCallbackOk);
        } catch (IdentityProviderException|ClientException) {
            /** @var string $loginRouteName */
            $loginRouteName = config('oauth.login_route_name');

            return Redirect::route($loginRouteName)
                ->with(['message' => __('oauth::translations.authentication-failed')]);
        }
    }

    /**
     * @deprecated Use {@see TokenRenewalService::renew()} instead. Kept for backward compatibility.
     */
    public function renew(): ?RedirectResponse
    {
        return app(TokenRenewalService::class)->renew();
    }
}
