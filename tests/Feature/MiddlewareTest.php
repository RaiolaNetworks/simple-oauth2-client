<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Raiolanetworks\OAuth\Middleware\OAuthTokenRenewal;
use Raiolanetworks\OAuth\Services\TokenRenewalService;
use Symfony\Component\HttpFoundation\Response;

it('calls the token renewal service and allows the request to proceed', function () {
    $mockService = Mockery::mock(TokenRenewalService::class);

    $mockService->shouldReceive('renew')->once()->andReturnNull();

    $middleware = new OAuthTokenRenewal($mockService);

    $response = $middleware->handle(Request::create('/test-url', 'GET'), fn () => new Response('Next middleware called'));

    expect($response->getContent())->toBe('Next middleware called');
});

it('returns the redirect response from renew when token renewal triggers a redirect', function () {
    $mockService = Mockery::mock(TokenRenewalService::class);

    $redirect = new RedirectResponse('/login');
    $mockService->shouldReceive('renew')->once()->andReturn($redirect);

    $middleware = new OAuthTokenRenewal($mockService);

    $response = $middleware->handle(Request::create('/test-url', 'GET'), fn () => new Response('Next middleware called'));

    expect($response)->toBe($redirect);
    expect($response->getStatusCode())->toBe(302);
});
