<?php

declare(strict_types=1);

use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

use function Pest\Laravel\get;
use function Pest\Laravel\instance;

use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Services\OAuthService;
use Raiolanetworks\OAuth\Services\TokenRenewalService;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

/**
 * Provider failures that must end at the login page, never in a 500.
 *
 * @return array<string, array{0: Closure(): Throwable}>
 */
function providerFailures(): array
{
    $request = new PsrRequest('POST', 'https://idp.test/application/o/token/');

    return [
        'timeout'            => [fn () => new ConnectException('Connection timed out', $request)],
        'server error'       => [fn () => new ServerException('Bad gateway', $request, new PsrResponse(502))],
        'malformed response' => [fn () => new UnexpectedValueException('Invalid response received from Authorization Server')],
    ];
}

function mockHandlers(): void
{
    instance(OAuthUserHandlerInterface::class, Mockery::mock(OAuthUserHandlerInterface::class));
    instance(OAuthGroupHandlerInterface::class, Mockery::mock(OAuthGroupHandlerInterface::class));
}

/**
 * @return list<string>
 */
function oauthRouteMiddleware(): array
{
    $router = new Router(app('events'), app());
    Route::swap($router);

    try {
        require __DIR__ . '/../../routes/web.php';

        $router->getRoutes()->refreshNameLookups();

        /** @var list<string> $middleware */
        $middleware = $router->getRoutes()->getByName('oauth.callback')?->gatherMiddleware() ?? [];

        return $middleware;
    } finally {
        Route::swap(app('router'));
    }
}

it('sends the user to the login page when the provider fails during the callback', function (Closure $failure) {
    mockHandlers();

    Session::put('oauth2-state', 'valid_state');
    Session::put('oauth2-pkceCode', 'valid_pkce_code');

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldReceive('setPkceCode');
    $mockOAuthService->shouldReceive('getAccessToken')->andThrow($failure());
    instance(OAuthService::class, $mockOAuthService);

    $response = get(route('oauth.callback', ['code' => 'valid_code', 'state' => 'valid_state']));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route(config('oauth.login_route_name')))
        ->and(session('message'))->toBe('Authentication failed. Please try again.');

    Mockery::close();
})->with(providerFailures());

it('rejects a code or state sent as an array', function (array $query) {
    mockHandlers();

    Session::put('oauth2-state', 'valid_state');
    Session::put('oauth2-pkceCode', 'valid_pkce_code');

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldNotReceive('getAccessToken');
    instance(OAuthService::class, $mockOAuthService);

    $response = get(route('oauth.callback', $query));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route(config('oauth.login_route_name')));

    Mockery::close();
})->with([
    'array code'  => [['code' => ['a', 'b'], 'state' => 'valid_state']],
    'array state' => [['code' => 'valid_code', 'state' => ['valid_state']]],
    'empty code'  => [['code' => '', 'state' => 'valid_state']],
]);

it('discards the state and verifier after a failed attempt', function () {
    mockHandlers();

    Session::put('oauth2-state', 'valid_state');
    Session::put('oauth2-pkceCode', 'valid_pkce_code');

    get(route('oauth.callback', ['code' => 'valid_code', 'state' => 'wrong_state']));

    expect(Session::has('oauth2-state'))->toBeFalse()
        ->and(Session::has('oauth2-pkceCode'))->toBeFalse();

    Mockery::close();
});

it('rejects an empty state even when the session has none', function () {
    mockHandlers();

    Session::put('oauth2-state', '');
    Session::put('oauth2-pkceCode', 'valid_pkce_code');

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldNotReceive('getAccessToken');
    instance(OAuthService::class, $mockOAuthService);

    $response = get(route('oauth.callback', ['code' => 'valid_code', 'state' => '']));

    expect($response->headers->get('Location'))->toBe(route(config('oauth.login_route_name')));

    Mockery::close();
});

/** @var TestCase $this */
it('logs out and invalidates the session when the provider fails during renewal', function (Closure $failure) {
    Config::set('oauth.offline_access', true);

    $mockUser  = TestUser::factory()->create();
    $oauthData = OAuth::factory(state: [
        'user_id'                => $mockUser->id,
        'oauth_token'            => 'some_token',
        'oauth_refresh_token'    => 'some_refresh_token',
        'oauth_token_expires_at' => Carbon::now()->subHour()->timestamp,
    ])->create();

    Session::put('previous-user-data', 'secret');
    $csrfBefore = Session::token();

    $mockOAuthService = Mockery::mock(OAuthService::class);
    $mockOAuthService->shouldReceive('getAccessToken')->andThrow($failure());
    instance(OAuthService::class, $mockOAuthService);

    $mockGuard = Mockery::mock(Guard::class, ['check' => true, 'user' => $mockUser]);
    $mockGuard->shouldReceive('logout')->once();
    Auth::shouldReceive('guard')->with(config('oauth.guard_name'))->andReturn($mockGuard);

    $response = $this->app->make(TokenRenewalService::class)->renew();

    expect($response?->getStatusCode())->toBe(302)
        ->and($response?->headers->get('Location'))->toBe(route(config('oauth.login_route_name')))
        ->and(Session::has('previous-user-data'))->toBeFalse()
        ->and(Session::token())->not->toBe($csrfBefore)
        ->and(Session::get('message'))->toBe('Your session has expired. Please log in again.')
        ->and($oauthData->refresh()->oauth_refresh_token)->toBeNull();

    Mockery::close();
})->with(providerFailures());

it('throttles the routes at 20 attempts per minute by default', function () {
    expect(oauthRouteMiddleware())->toContain('throttle:20,1');
});

it('applies the configured throttle, or none when empty', function (?string $value, ?string $expected) {
    Config::set('oauth.throttle', $value);

    $throttles = array_values(array_filter(oauthRouteMiddleware(), fn (string $m) => str_starts_with($m, 'throttle')));

    expect($throttles)->toBe($expected === null ? [] : [$expected]);
})->with([
    'custom'   => ['60,1', 'throttle:60,1'],
    'disabled' => ['', null],
    'null'     => [null, null],
]);
