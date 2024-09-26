<?php

declare(strict_types=1);

use Raiolanetworks\OAuth\OAuth;

it('returns the correct facade accessor', function () {
    $oauth = new OAuth();

    $reflection = new ReflectionClass($oauth);
    $method     = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    $result = $method->invoke($oauth);

    expect($result)->toBe('oauth');
});
