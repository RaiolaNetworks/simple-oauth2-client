<?php

declare(strict_types=1);

use Raiolanetworks\OAuth\Facades\OAuth as OAuthFacade;
use Raiolanetworks\OAuth\Services\OAuthService;

it('ensures the OAuth facade works as expected', function () {
    $mockOAuth = Mockery::mock(OAuthService::class);

    $mockOAuth->shouldReceive('someMethod')
        ->once()
        ->andReturn('mocked response');

    $this->instance('oauth', $mockOAuth);

    $response = OAuthFacade::someMethod();

    expect($response)->toBe('mocked response');
});
