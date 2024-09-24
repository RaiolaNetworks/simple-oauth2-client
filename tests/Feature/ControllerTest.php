<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;

use function Pest\Laravel\get;
use function Pest\Laravel\instance;

use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Controllers\OAuthController;
use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Services\OAuthService;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

it('redirect authenticated users to the homepage', function () {
    instance(OAuthUserHandlerInterface::class, Mockery::mock(OAuthUserHandlerInterface::class));
    instance(OAuthGroupHandlerInterface::class, Mockery::mock(OAuthGroupHandlerInterface::class));

    $mockGuard = Mockery::mock(Guard::class);
    $mockGuard->shouldReceive('check')->andReturnTrue();

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn($mockGuard);

    $response = get(route('oauth.request'));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(Redirect::to('/')->getTargetUrl());

    Mockery::close();
});

/** @var TestCase $this */
it('redirects unauthenticated users to the OAuth provider', function () {
    instance(OAuthUserHandlerInterface::class, Mockery::mock(OAuthUserHandlerInterface::class));
    instance(OAuthGroupHandlerInterface::class, Mockery::mock(OAuthGroupHandlerInterface::class));

    $mockGuard = Mockery::mock(Guard::class);
    $mockGuard->shouldReceive('check')->andReturnFalse();

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn($mockGuard);

    $mockProvider = Mockery::mock(OAuthService::class);
    $mockProvider->shouldReceive('getAuthorizationUrl')
        ->with(['prompt' => 'consent'])
        ->andReturn('https://example.com/oauth/authorize');

    $mockProvider->shouldReceive('getState')
        ->andReturn('fake_state');

    $mockProvider->shouldReceive('getPkceCode')
        ->andReturn('fake_pkce_code');

    instance(OAuthService::class, $mockProvider);

    $response = get(route('oauth.request'));

    $this->assertEquals('fake_state', Session::get('oauth2-state'));
    $this->assertEquals('fake_pkce_code', Session::get('oauth2-pkceCode'));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe('https://example.com/oauth/authorize');

    Mockery::close();
});

it('redirect where the user intends to go if authenticated in the callback', function () {
    instance(OAuthUserHandlerInterface::class, Mockery::mock(OAuthUserHandlerInterface::class));
    instance(OAuthGroupHandlerInterface::class, Mockery::mock(OAuthGroupHandlerInterface::class));

    $mockGuard = Mockery::mock(Guard::class);
    $mockGuard->shouldReceive('check')->andReturnTrue();

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn($mockGuard);

    $response = get(route('oauth.callback'));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(Redirect::intended()->getTargetUrl());

    Mockery::close();
});

it('handles invalid or missing code in callback', function () {
    instance(OAuthUserHandlerInterface::class, Mockery::mock(OAuthUserHandlerInterface::class));
    instance(OAuthGroupHandlerInterface::class, Mockery::mock(OAuthGroupHandlerInterface::class));

    Session::put('oauth2-state', 'correct_state');

    $response = get(route('oauth.callback'), [
        'state' => 'correct_state',
    ]);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(route(config('oauth.login_route_name')));
    expect(session('message'))->toBe('Authentication failed. Please try again.');

    Mockery::close();
});

it('handles invalid state in callback', function () {
    instance(OAuthUserHandlerInterface::class, Mockery::mock(OAuthUserHandlerInterface::class));
    instance(OAuthGroupHandlerInterface::class, Mockery::mock(OAuthGroupHandlerInterface::class));

    $response = get(route('oauth.callback', [
        'code' => 'valid_code',
    ]));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(route(config('oauth.login_route_name')));
    expect(session('message'))->toBe('Authentication failed. Please try again.');

    Mockery::close();
});

it('logs in the user after a successful OAuth callback', function () {
    $mockOAuthService               = Mockery::mock(OAuthService::class);
    $mockOAuthUserHandlerInterface  = Mockery::mock(OAuthUserHandlerInterface::class);
    $mockOAuthGroupHandlerInterface = Mockery::mock(OAuthGroupHandlerInterface::class);

    $pkceCode  = 'valid_pkce_code';
    $stateCode = 'valid_state';
    $validCode = 'valid_code';

    Session::put('oauth2-state', $stateCode);
    Session::put('oauth2-pkceCode', $pkceCode);

    Config::set('oauth.user_model_name', TestUser::class);

    $mockOAuthService->shouldReceive('setPkceCode')
        ->with($pkceCode)
        ->once();

    $mockAccessToken = Mockery::mock(AccessToken::class);
    $mockAccessToken->shouldReceive('getToken')->andReturn('mock_token');
    $mockAccessToken->shouldReceive('getRefreshToken')->andReturn('mock_refresh_token');
    $mockAccessToken->shouldReceive('getExpires')->andReturn(time() + 3600);

    $mockOAuthService->shouldReceive('getAccessToken')
        ->with('authorization_code', ['code' => $validCode])
        ->andReturn($mockAccessToken);

    $mockOAuthService->shouldReceive('getResourceOwner')
        ->andReturn(Mockery::mock([
            'toArray' => [
                'id'     => 1,
                'name'   => 'user',
                'email'  => 'user@example.com',
                'groups' => ['admin'],
                'sub'    => '123456abc',
            ],
        ]));

    $mockOAuthUserHandlerInterface->shouldReceive('handleUser')
        ->andReturn(TestUser::factory()->create());
    $mockOAuthGroupHandlerInterface->shouldReceive('handleGroups')
        ->andReturn();

    instance(OAuthService::class, $mockOAuthService);
    instance(OAuthUserHandlerInterface::class, $mockOAuthUserHandlerInterface);
    instance(OAuthGroupHandlerInterface::class, $mockOAuthGroupHandlerInterface);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['login' => null, 'check' => false]));

    $response = get(route('oauth.callback', [
        'code'  => $validCode,
        'state' => $stateCode,
    ]));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(Redirect::route(config('oauth.redirect_route_name_callback_ok'))->getTargetUrl());

    Mockery::close();
});

/** @var TestCase $this */
it('renews the OAuth token if the user is authenticated and the token is expired', function () {
    $mockOAuthService               = Mockery::mock(OAuthService::class);
    $mockOAuthUserHandlerInterface  = Mockery::mock(OAuthUserHandlerInterface::class);
    $mockOAuthGroupHandlerInterface = Mockery::mock(OAuthGroupHandlerInterface::class);

    Config::set('oauth.offline_access', true);

    $newOAuthToken   = 'new_oauth_token';
    $newRefreshToken = 'new_refresh_token';
    $newExpiredDate  = Carbon::now()->subHour()->timestamp;

    $mockUser  = TestUser::factory()->create();
    $oauthData = OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => $newOAuthToken,
        'oauth_refresh_token'    => $newRefreshToken,
        'oauth_token_expires_at' => $newExpiredDate,
    ])->create();

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser]));

    $mockAccessToken = Mockery::mock(AccessToken::class);
    $mockAccessToken->shouldReceive('getToken')->andReturn($newOAuthToken);
    $mockAccessToken->shouldReceive('getRefreshToken')->andReturn($newRefreshToken);
    $mockAccessToken->shouldReceive('getExpires')->andReturn($newExpiredDate);

    $mockOAuthService->shouldReceive('getAccessToken')
        ->with('refresh_token', ['refresh_token' => $newRefreshToken])
        ->andReturn($mockAccessToken);

    $mockOAuthService->shouldReceive('getResourceOwner')
        ->andReturn(Mockery::mock(['toArray' => [
            'groups' => ['admin'],
        ]]));

    $mockOAuthUserHandlerInterface->shouldReceive('handleUser')
        ->andReturn($mockUser);
    $mockOAuthGroupHandlerInterface->shouldReceive('handleGroups')
        ->andReturn();

    instance(OAuthService::class, $mockOAuthService);
    instance(OAuthUserHandlerInterface::class, $mockOAuthUserHandlerInterface);
    instance(OAuthGroupHandlerInterface::class, $mockOAuthGroupHandlerInterface);

    $response = $this->app->make(OAuthController::class)->renew();

    expect($oauthData->oauth_token)->toBe($newOAuthToken);
    expect($oauthData->oauth_refresh_token)->toBe($newRefreshToken);
    expect($oauthData->oauth_token_expires_at)->toBe($newExpiredDate);

    expect($response)->toBeNull();

    Mockery::close();
});

/** @var TestCase $this */
it('logs out the user if there is an error during token renewal', function () {
    $mockOAuthService               = Mockery::mock(OAuthService::class);
    $mockOAuthUserHandlerInterface  = Mockery::mock(OAuthUserHandlerInterface::class);
    $mockOAuthGroupHandlerInterface = Mockery::mock(OAuthGroupHandlerInterface::class);

    Config::set('oauth.offline_access', true);

    $mockUser = TestUser::factory()->create();
    OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_refresh_token'    => null,
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
    ])->create();

    $mockGuard = Mockery::mock(Guard::class);
    $mockGuard->shouldReceive('check')->andReturn(true);
    $mockGuard->shouldReceive('user')->andReturn($mockUser);
    $mockGuard->shouldReceive('logout')->once();

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn($mockGuard);

    $mockOAuthService->shouldReceive('getAccessToken')
        ->with('refresh_token', ['refresh_token' => null])
        ->andThrow(IdentityProviderException::class);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['logout' => null]));

    instance(OAuthService::class, $mockOAuthService);
    instance(OAuthUserHandlerInterface::class, $mockOAuthUserHandlerInterface);
    instance(OAuthGroupHandlerInterface::class, $mockOAuthGroupHandlerInterface);

    $response = $this->app->make(OAuthController::class)->renew();

    expect($mockUser->oauth_token)->toBeNull();
    expect($mockUser->oauth_refresh_token)->toBeNull();
    expect($mockUser->oauth_token_expires_at)->toBeNull();

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(route(config('oauth.login_route_name')));
    expect(session('message'))->toBe('Your session has expired. Please log in again.');

    Mockery::close();
});

/** @var TestCase $this */
it('logs out the user if offline_access is false during token renewal', function () {
    $mockOAuthService               = Mockery::mock(OAuthService::class);
    $mockOAuthUserHandlerInterface  = Mockery::mock(OAuthUserHandlerInterface::class);
    $mockOAuthGroupHandlerInterface = Mockery::mock(OAuthGroupHandlerInterface::class);


    $mockUser = TestUser::factory()->create();
    OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
        ])->create();

    Config::set('oauth.offline_access', false);

    $mockGuard = Mockery::mock(Guard::class);
    $mockGuard->shouldReceive('check')->andReturn(true);
    $mockGuard->shouldReceive('user')->andReturn($mockUser);
    $mockGuard->shouldReceive('logout')->once();

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn($mockGuard);

    instance(OAuthService::class, $mockOAuthService);
    instance(OAuthUserHandlerInterface::class, $mockOAuthUserHandlerInterface);
    instance(OAuthGroupHandlerInterface::class, $mockOAuthGroupHandlerInterface);

    $response = $this->app->make(OAuthController::class)->renew();

    expect($mockUser->oauth_token)->toBeNull();
    expect($mockUser->oauth_refresh_token)->toBeNull();
    expect($mockUser->oauth_token_expires_at)->toBeNull();

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(route(config('oauth.login_route_name')));
    expect(session('message'))->toBe('Your session has expired. Please log in again.');

    Mockery::close();
});
