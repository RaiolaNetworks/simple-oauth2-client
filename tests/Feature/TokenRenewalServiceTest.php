<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

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
it('caches the token expiry after a successful renewal', function () {
    Config::set('oauth.offline_access', true);

    $expiresAt = Carbon::now()->addHour()->timestamp;

    $service = $this->app->make(TokenRenewalService::class);
    $service->rememberExpiry($expiresAt);

    expect(Session::get(TokenRenewalService::EXPIRY_SESSION_KEY))->toBe($expiresAt);
});
