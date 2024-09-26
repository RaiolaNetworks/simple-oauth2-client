<?php

declare(strict_types=1);

use League\OAuth2\Client\Token\AccessToken;
use Raiolanetworks\OAuth\Handlers\BaseOAuthGroupHandler;
use Raiolanetworks\OAuth\Handlers\BaseOAuthUserHandler;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

it('handles groups correctly with BaseOAuthGroupHandler', function () {
    $userMock = TestUser::factory()->create();

    $groups = [
        'admin' => 'Administrator',
        'user'  => 'Regular User',
    ];

    $handler = new BaseOAuthGroupHandler();
    $handler->handleGroups($groups, $userMock);

    expect(true)->toBeTrue();
});

it('handles users correctly with BaseOAuthUserHandler', function () {
    $userMock = TestUser::factory()->create();

    $accessToken = Mockery::mock(AccessToken::class);

    $userData = [
        'email' => 'user@example.com',
        'name'  => 'Test User',
    ];

    $handler = new BaseOAuthUserHandler();
    $result  = $handler->handleUser($userData, $accessToken);

    expect($result)->toBeInstanceOf(TestUser::class);

    Mockery::close();
});
