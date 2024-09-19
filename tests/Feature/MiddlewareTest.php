<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Raiolanetworks\OAuth\Controllers\OAuthController;
use Raiolanetworks\OAuth\Middleware\OAuthTokenRenewal;
use Symfony\Component\HttpFoundation\Response;

it('calls the OAuthController renew method and allows the request to proceed', function () {
    $mockController = Mockery::mock(OAuthController::class);

    $mockController->shouldReceive('renew')->once();

    $middleware = new OAuthTokenRenewal();

    $this->instance(OAuthController::class, $mockController);

    $response = $middleware->handle(Request::create('/test-url', 'GET'), fn () => new Response('Next middleware called'));

    expect($response->getContent())->toBe('Next middleware called');
});
