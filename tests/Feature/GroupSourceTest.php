<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Token\AccessToken;

use function Pest\Laravel\get;
use function Pest\Laravel\instance;

use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Enums\GroupSourceEnum;
use Raiolanetworks\OAuth\Events\OAuthTokenUpdated;
use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Services\OAuthService;
use Raiolanetworks\OAuth\Services\TokenRenewalService;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

/**
 * Resource owner with both claims, and a directory group named like the
 * entitlement: only the configured source may reach the handler.
 *
 * @return array<string, mixed>
 */
function resourceOwnerWithBothClaims(): array
{
    return [
        'sub'          => '123456abc',
        'groups'       => ['app-admin', 'Staff'],
        'entitlements' => ['app-editor'],
    ];
}

function requestedScopes(): string
{
    $query = parse_url((new OAuthService())->getAuthorizationUrl(), PHP_URL_QUERY);
    parse_str(is_string($query) ? $query : '', $params);

    return is_string($params['scope'] ?? null) ? $params['scope'] : '';
}

it('defaults to groups when the option is missing or empty', function (?string $value) {
    Config::set('oauth.group_source', $value);

    expect(GroupSourceEnum::fromConfig())->toBe(GroupSourceEnum::GROUPS);
})->with([null, '']);

it('reads the configured source, ignoring case and spaces', function (string $value, GroupSourceEnum $expected) {
    Config::set('oauth.group_source', $value);

    expect(GroupSourceEnum::fromConfig())->toBe($expected);
})->with([
    ['groups', GroupSourceEnum::GROUPS],
    ['entitlements', GroupSourceEnum::ENTITLEMENTS],
    [' Entitlements ', GroupSourceEnum::ENTITLEMENTS],
]);

it('throws on an unknown source instead of falling back to groups', function () {
    Config::set('oauth.group_source', 'entitlement');

    GroupSourceEnum::fromConfig();
})->throws(InvalidArgumentException::class, 'oauth.group_source');

it('extracts only the configured claim', function () {
    expect(GroupSourceEnum::GROUPS->extract(resourceOwnerWithBothClaims()))->toBe(['app-admin', 'Staff'])
        ->and(GroupSourceEnum::ENTITLEMENTS->extract(resourceOwnerWithBothClaims()))->toBe(['app-editor']);
});

it('never falls back to the groups when the entitlements claim is missing', function () {
    expect(GroupSourceEnum::ENTITLEMENTS->extract(['sub' => 'x', 'groups' => ['app-admin']]))->toBe([]);
});

it('ignores malformed claims and non-string values', function () {
    expect(GroupSourceEnum::ENTITLEMENTS->extract(['entitlements' => 'app-admin']))->toBe([])
        ->and(GroupSourceEnum::ENTITLEMENTS->extract(['entitlements' => ['a', 1, null, ['b'], 'c']]))->toBe(['a', 'c']);
});

it('requests the entitlements scope only in entitlements mode', function (string $source, bool $offlineAccess, string $expected) {
    Config::set('oauth.group_source', $source);
    Config::set('oauth.offline_access', $offlineAccess);

    expect(requestedScopes())->toBe($expected);
})->with([
    ['groups', false, 'openid profile email'],
    ['groups', true, 'openid profile email offline_access'],
    ['entitlements', false, 'openid profile email entitlements'],
    ['entitlements', true, 'openid profile email offline_access entitlements'],
]);

/** @var TestCase $this */
it('hands the configured claim to the group handler and the event on callback', function (string $source, array $expected) {
    Config::set('oauth.group_source', $source);
    Config::set('oauth.user_model_name', TestUser::class);
    Event::fake([OAuthTokenUpdated::class]);

    Session::put('oauth2-state', 'valid_state');
    Session::put('oauth2-pkceCode', 'valid_pkce_code');

    $mockAccessToken = Mockery::mock(AccessToken::class);
    $mockAccessToken->shouldReceive('getToken')->andReturn('mock_token');
    $mockAccessToken->shouldReceive('getRefreshToken')->andReturn('mock_refresh_token');
    $mockAccessToken->shouldReceive('getExpires')->andReturn(time() + 3600);

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldReceive('setPkceCode')->with('valid_pkce_code')->once();
    $mockOAuthService->shouldReceive('getAccessToken')->andReturn($mockAccessToken);
    $mockOAuthService->shouldReceive('getResourceOwner')
        ->andReturn(Mockery::mock(['toArray' => resourceOwnerWithBothClaims()]));

    $mockUserHandler = Mockery::mock(OAuthUserHandlerInterface::class);
    $mockUserHandler->shouldReceive('handleUser')->andReturn(TestUser::factory()->create());

    $mockGroupHandler = Mockery::mock(OAuthGroupHandlerInterface::class);
    $mockGroupHandler->shouldReceive('handleGroups')->once()->with($expected, Mockery::any());

    instance(OAuthService::class, $mockOAuthService);
    instance(OAuthUserHandlerInterface::class, $mockUserHandler);
    instance(OAuthGroupHandlerInterface::class, $mockGroupHandler);

    Auth::shouldReceive('guard')
        ->andReturn(Mockery::mock(Guard::class, ['login' => null, 'check' => false]));
    Auth::shouldReceive('userResolver')->andReturn(fn () => null);

    $response = get(route('oauth.callback', ['code' => 'valid_code', 'state' => 'valid_state']));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route(config('oauth.redirect_route_name_callback_ok')));

    Event::assertDispatched(OAuthTokenUpdated::class, fn (OAuthTokenUpdated $event) => $event->groups === $expected);

    Mockery::close();
})->with([
    'groups'       => ['groups', ['app-admin', 'Staff']],
    'entitlements' => ['entitlements', ['app-editor']],
]);

/** @var TestCase $this */
it('dispatches the entitlements on token renewal in entitlements mode', function () {
    Config::set('oauth.group_source', 'entitlements');
    Config::set('oauth.offline_access', true);
    Event::fake([OAuthTokenUpdated::class]);

    $mockUser = TestUser::factory()->create();

    OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => 'some_token',
        'oauth_refresh_token'    => 'some_refresh_token',
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
    ])->create();

    $mockAccessToken = Mockery::mock(AccessToken::class);
    $mockAccessToken->shouldReceive('getToken')->andReturn('renewed_token');
    $mockAccessToken->shouldReceive('getRefreshToken')->andReturn('renewed_refresh_token');
    $mockAccessToken->shouldReceive('getExpires')->andReturn(Carbon::now()->addHour()->timestamp);

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldReceive('getAccessToken')->andReturn($mockAccessToken);
    $mockOAuthService->shouldReceive('getResourceOwner')
        ->andReturn(Mockery::mock(['toArray' => resourceOwnerWithBothClaims()]));
    instance(OAuthService::class, $mockOAuthService);

    Auth::shouldReceive('guard')
        ->with(config('oauth.guard_name'))
        ->andReturn(Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser]));

    expect($this->app->make(TokenRenewalService::class)->renew())->toBeNull();

    Event::assertDispatched(OAuthTokenUpdated::class, fn (OAuthTokenUpdated $event) => $event->groups === ['app-editor']);

    Mockery::close();
});
