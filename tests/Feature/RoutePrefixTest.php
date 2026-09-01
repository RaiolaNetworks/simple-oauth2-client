<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Raiolanetworks\OAuth\Services\OAuthService;

/**
 * Reads back the redirect URI the package announces to the provider.
 */
function advertisedRedirectUri(): string
{
    $authorizationUrl = app(OAuthService::class)->getAuthorizationUrl();

    parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);

    /** @var string $redirectUri */
    $redirectUri = $query['redirect_uri'];

    return $redirectUri;
}

/** @var TestCase $this */
it('announces the redirect uri of the registered callback route', function () {
    expect(route('oauth.callback'))->toEndWith('/oauth/callback');
    expect(advertisedRedirectUri())->toBe(route('oauth.callback'));
});

/** @var TestCase $this */
it('ignores the deprecated callback uri setting', function () {
    Config::set('oauth.callback', '/somewhere/else');

    expect(advertisedRedirectUri())->toEndWith('/oauth/callback');
});

/** @var TestCase $this */
it('serves the routes under the configured prefix', function () {
    Config::set('oauth.route_prefix', 'auth');

    $router = new Router(app('events'), app());
    Route::swap($router);

    try {
        require __DIR__ . '/../../routes/web.php';

        $router->getRoutes()->refreshNameLookups();

        expect($router->getRoutes()->getByName('oauth.request')?->uri())->toBe('auth/request');
        expect($router->getRoutes()->getByName('oauth.callback')?->uri())->toBe('auth/callback');
    } finally {
        Route::swap($this->app->make('router'));
    }
});

/** @var TestCase $this */
it('roots the redirect uri in app.url instead of the incoming request', function () {
    Config::set('app.url', 'https://midominio.com');

    // Stands in for what changes the generator root in production: a reverse
    // proxy terminating TLS, a spoofed Host header, or serving under several
    // hostnames. The advertised URI must not follow any of them.
    URL::forceRootUrl('http://otro-host.example:8080');

    expect(advertisedRedirectUri())->toBe('https://midominio.com/oauth/callback');
});

/** @var TestCase $this */
it('keeps the redirect uri rooted in app.url when it carries a trailing slash', function () {
    Config::set('app.url', 'https://midominio.com/');

    expect(advertisedRedirectUri())->toBe('https://midominio.com/oauth/callback');
});
