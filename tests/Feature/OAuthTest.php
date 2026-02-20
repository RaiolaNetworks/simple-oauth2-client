<?php

declare(strict_types=1);

use Raiolanetworks\OAuth\Facades\OAuth;

it('returns the correct facade accessor', function () {
    $reflection = new ReflectionClass(OAuth::class);
    $method     = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    $result = $method->invoke(null);

    expect($result)->toBe('oauth');
});
