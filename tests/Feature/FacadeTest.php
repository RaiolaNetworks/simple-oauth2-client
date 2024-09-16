<?php

declare(strict_types=1);

use Raiolanetworks\OAuth\Facades\OAuth as OAuthFacade;
use Raiolanetworks\OAuth\OAuth;

it('ensures the OAuth facade works as expected', function () {
    $mockOAuth = Mockery::mock(OAuth::class);

    $mockOAuth->shouldReceive('someMethod')
        ->once()
        ->andReturn('mocked response');

    $this->instance(OAuth::class, $mockOAuth);

    $response = OAuthFacade::someMethod();

    expect($response)->toBe('mocked response');
});
