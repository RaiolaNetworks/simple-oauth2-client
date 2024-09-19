<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Controllers;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Livewire\Features\SupportRedirects\Redirector;
use Raiolanetworks\OAuth\Events\EventsOAuthTokenUpdated;
use Raiolanetworks\OAuth\Services\OAuthService;
use Symfony\Component\HttpFoundation\RedirectResponse as HttpFoundationRedirectResponse;

class OAuthController extends Controller
{
    private OAuthService $provider;

    public function __construct(?OAuthService $provider = null)
    {
        $this->provider = $provider ?? app(OAuthService::class);
    }

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

    public function callback(): RedirectResponse
    {
        /** @var string $guardName */
        $guardName = config('oauth.guard_name');

        if (Auth::guard($guardName)->check()) {
            return Redirect::intended();
        }

        try {
            $session = Session::all();

            $code  = Request::get('code');
            $state = Request::get('state');

            if (! isset($code)) {
                throw new IdentityProviderException('Invalid code', 0, 'Invalid code');
            }

            if (! isset($state) || ! isset($session['oauth2-state']) || $state !== $session['oauth2-state']) {
                throw new IdentityProviderException('Invalid state', 0, 'Invalid state');
            }

            $this->provider->setPkceCode($session['oauth2-pkceCode']);

            /** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $callback = $this->provider->getResourceOwner($accessToken)->toArray();
            $user     = $this->updateOrCreateUser($callback, $accessToken);

            EventsOAuthTokenUpdated::dispatch($user, $callback['groups']);
            Session::remove('oauth2-state');
            Session::remove('oauth2-pkceCode');

            /** @var Authenticatable $user */
            Auth::guard($guardName)->login($user);

            /** @var string $redirectRouteCallbackOk */
            $redirectRouteCallbackOk = config('oauth.redirect_route_callback_ok');

            return Redirect::to($redirectRouteCallbackOk);
        } catch (IdentityProviderException|ClientException) {
            /** @var string $loginRouteName */
            $loginRouteName = config('oauth.login_route_name');

            return Redirect::route($loginRouteName)
                ->with(['message' => 'Authentication failed. Please try again.']);
        }
    }

    public function renew(): null|\Illuminate\Routing\Redirector|RedirectResponse
    {
        /** @var string $guardName */
        $guardName = config('oauth.guard_name');

        if (Auth::guard($guardName)->check()) {
            /** @var Authenticatable $user */
            $user = Auth::guard($guardName)->user();

            // @phpstan-ignore-next-line
            if ($user->oauth_token !== null && $user->oauth_token_expires_at->timestamp < Carbon::now()->timestamp) {
                try {
                    /** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
                    $accessToken = $this->provider->getAccessToken('refresh_token', [
                        'refresh_token' => $user->oauth_refresh_token, // @phpstan-ignore-line
                    ]);

                    $resourceOwner = $this->provider->getResourceOwner($accessToken);
                    $callback      = $resourceOwner->toArray();
                } catch (IdentityProviderException|ClientException) {
                    /** @var Model $user */
                    $user->update([
                        'oauth_token'            => null,
                        'oauth_refresh_token'    => null,
                        'oauth_token_expires_at' => null,
                    ]);

                    Auth::guard($guardName)->logout();

                    /** @var string $loginRouteName */
                    $loginRouteName = config('oauth.login_route_name');

                    return Redirect::route($loginRouteName)
                        ->with(['message' => 'Your session has expired. Please log in again.']);
                }

                /** @var Model $user */
                $user->update([
                    'oauth_token'            => $accessToken->getToken(),
                    'oauth_refresh_token'    => $accessToken->getRefreshToken(),
                    'oauth_token_expires_at' => $accessToken->getExpires(),
                ]);

                EventsOAuthTokenUpdated::dispatch($user, $callback['groups']);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $callback
     */
    protected function updateOrCreateUser(array $callback, AccessTokenInterface $accessToken): Model
    {
        /** @var array<string,string> $groups */
        $groups = $callback['groups'] ?? [];

        /** @var Model $model */
        $model = config('oauth.user_model_name');

        return (new $model())::updateOrCreate(
            [
                'email'    => $callback['email'],
                'oauth_id' => $callback['sub'],
            ],
            [
                'name'                   => $callback['name'],
                'type'                   => in_array(config('oauth.admin_group'), $groups) ? 'admin' : 'user',
                'oauth_token'            => $accessToken->getToken(),
                'oauth_refresh_token'    => $accessToken->getRefreshToken(),
                'oauth_token_expires_at' => $accessToken->getExpires(),
            ]
        );
    }
}
