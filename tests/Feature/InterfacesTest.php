<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Handlers\BaseOAuthGroupHandler;
use Raiolanetworks\OAuth\Handlers\BaseOAuthUserHandler;
use Raiolanetworks\OAuth\OAuthServiceProvider;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

class CustomUserHandler implements OAuthUserHandlerInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function handleUser(array $data, AccessTokenInterface $accessToken): Model
    {
        return TestUser::factory()->create();
    }
}

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

/** @var TestCase $this */
it('resolves both handlers when the package registers before its config is merged', function () {
    // Drop the config the way a fresh install has it, then run the provider's
    // registration again: the package's own defaults have to carry it through.
    Config::set('oauth', []);

    (new OAuthServiceProvider($this->app))->register();

    expect($this->app->make(OAuthUserHandlerInterface::class))->toBeInstanceOf(BaseOAuthUserHandler::class);
    expect($this->app->make(OAuthGroupHandlerInterface::class))->toBeInstanceOf(BaseOAuthGroupHandler::class);
});

/** @var TestCase $this */
it('resolves the configured handler when the config changes at runtime', function () {
    Config::set('oauth.user_handler', CustomUserHandler::class);

    expect($this->app->make(OAuthUserHandlerInterface::class))->toBeInstanceOf(CustomUserHandler::class);
});
