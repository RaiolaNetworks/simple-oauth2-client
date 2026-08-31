<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Token\AccessToken;

use function Pest\Laravel\instance;

use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Services\OAuthService;
use Raiolanetworks\OAuth\Services\TokenRenewalService;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

/** @var TestCase $this */
it('skips the database lookup and provider when the cached expiry is still valid', function () {
    Config::set('oauth.offline_access', true);

    $mockUser = TestUser::factory()->create();

    // Expired token in the database: without the cache this would trigger a renewal.
    OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => 'some_token',
        'oauth_refresh_token'    => 'some_refresh_token',
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
    ])->create();

    // Cached expiry says the token is still valid.
    Session::put(TokenRenewalService::EXPIRY_SESSION_KEY, Carbon::now()->addHour()->timestamp);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser]));

    // The provider must never be touched on the fast path.
    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldNotReceive('getAccessToken');
    $mockOAuthService->shouldNotReceive('getResourceOwner');
    instance(OAuthService::class, $mockOAuthService);

    $response = $this->app->make(TokenRenewalService::class)->renew();

    expect($response)->toBeNull();

    Mockery::close();
});

/** @var TestCase $this */
it('keeps the stored refresh token when the provider does not return a new one', function () {
    Config::set('oauth.offline_access', true);

    $mockUser = TestUser::factory()->create();

    $oauthData = OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => 'some_token',
        'oauth_refresh_token'    => 'some_refresh_token',
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
    ])->create();

    $expiresAt = Carbon::now()->addHour()->timestamp;

    $mockAccessToken = Mockery::mock(AccessToken::class);
    $mockAccessToken->shouldReceive('getToken')->andReturn('renewed_token');
    $mockAccessToken->shouldReceive('getRefreshToken')->andReturn(null);
    $mockAccessToken->shouldReceive('getExpires')->andReturn($expiresAt);

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldReceive('getAccessToken')
        ->with('refresh_token', ['refresh_token' => 'some_refresh_token'])
        ->andReturn($mockAccessToken);
    $mockOAuthService->shouldReceive('getResourceOwner')
        ->andReturn(Mockery::mock(['toArray' => ['sub' => '123456abc', 'groups' => ['admin']]]));
    instance(OAuthService::class, $mockOAuthService);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser]));

    $response = $this->app->make(TokenRenewalService::class)->renew();

    expect($response)->toBeNull();

    $oauthData->refresh();

    expect($oauthData->oauth_refresh_token)->toBe('some_refresh_token');
    expect($oauthData->oauth_token)->toBe('renewed_token');
    expect($oauthData->oauth_token_expires_at)->toBe($expiresAt);

    Mockery::close();
});

/** @var TestCase $this */
it('stores the new refresh token when the provider rotates it', function () {
    Config::set('oauth.offline_access', true);

    $mockUser = TestUser::factory()->create();

    $oauthData = OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => 'some_token',
        'oauth_refresh_token'    => 'some_refresh_token',
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
    ])->create();

    $mockAccessToken = Mockery::mock(AccessToken::class);
    $mockAccessToken->shouldReceive('getToken')->andReturn('renewed_token');
    $mockAccessToken->shouldReceive('getRefreshToken')->andReturn('rotated_refresh_token');
    $mockAccessToken->shouldReceive('getExpires')->andReturn(Carbon::now()->addHour()->timestamp);

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldReceive('getAccessToken')->andReturn($mockAccessToken);
    $mockOAuthService->shouldReceive('getResourceOwner')
        ->andReturn(Mockery::mock(['toArray' => ['sub' => '123456abc']]));
    instance(OAuthService::class, $mockOAuthService);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser]));

    $this->app->make(TokenRenewalService::class)->renew();

    $oauthData->refresh();

    expect($oauthData->oauth_refresh_token)->toBe('rotated_refresh_token');

    Mockery::close();
});

/** @var TestCase $this */
it('logs the user out instead of calling the provider without a refresh token', function () {
    Config::set('oauth.offline_access', true);

    $mockUser = TestUser::factory()->create();

    $oauthData = OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => 'some_token',
        'oauth_refresh_token'    => null,
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
    ])->create();

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldNotReceive('getAccessToken');
    instance(OAuthService::class, $mockOAuthService);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser, 'logout' => null]));

    $response = $this->app->make(TokenRenewalService::class)->renew();

    expect($response?->getTargetUrl())->toBe(route(config('oauth.login_route_name')));

    $oauthData->refresh();

    expect($oauthData->oauth_token)->toBeNull();
    expect($oauthData->oauth_token_expires_at)->toBeNull();

    Mockery::close();
});

/** @var TestCase $this */
it('renews a token that expires within the safety margin', function () {
    Config::set('oauth.offline_access', true);

    $mockUser = TestUser::factory()->create();

    $expiringSoon = Carbon::now()->addSeconds(TokenRenewalService::EXPIRY_MARGIN_SECONDS - 30)->timestamp;

    OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => 'some_token',
        'oauth_refresh_token'    => 'some_refresh_token',
        'oauth_token_expires_at' => $expiringSoon,
    ])->create();

    Session::put(TokenRenewalService::EXPIRY_SESSION_KEY, $expiringSoon);

    $mockAccessToken = Mockery::mock(AccessToken::class);
    $mockAccessToken->shouldReceive('getToken')->andReturn('renewed_token');
    $mockAccessToken->shouldReceive('getRefreshToken')->andReturn('rotated_refresh_token');
    $mockAccessToken->shouldReceive('getExpires')->andReturn(Carbon::now()->addHour()->timestamp);

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldReceive('getAccessToken')->once()->andReturn($mockAccessToken);
    $mockOAuthService->shouldReceive('getResourceOwner')
        ->andReturn(Mockery::mock(['toArray' => ['sub' => '123456abc']]));
    instance(OAuthService::class, $mockOAuthService);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser]));

    $response = $this->app->make(TokenRenewalService::class)->renew();

    expect($response)->toBeNull();

    Mockery::close();
});

/** @var TestCase $this */
it('caches the token expiry after a successful renewal', function () {
    Config::set('oauth.offline_access', true);

    $expiresAt = Carbon::now()->addHour()->timestamp;

    $service = $this->app->make(TokenRenewalService::class);
    $service->rememberExpiry($expiresAt);

    expect(Session::get(TokenRenewalService::EXPIRY_SESSION_KEY))->toBe($expiresAt);
});
