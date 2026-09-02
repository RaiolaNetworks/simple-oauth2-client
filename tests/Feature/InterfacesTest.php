<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
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

/** @var TestCase $this */
it('gives a new user a password so the default users table accepts the insert', function () {
    $handler = new BaseOAuthUserHandler();

    $user = $handler->handleUser(
        ['email' => 'nueva@example.com', 'name' => 'Ana'],
        Mockery::mock(AccessToken::class)
    );

    expect($user->exists)->toBeTrue();
    expect($user->getAttribute('password'))->toBeString()->not->toBeEmpty();

    Mockery::close();
});

/** @var TestCase $this */
it('never rewrites the password of a user that logs in again', function () {
    $handler = new BaseOAuthUserHandler();
    $token   = Mockery::mock(AccessToken::class);

    $first    = $handler->handleUser(['email' => 'ana@example.com', 'name' => 'Ana'], $token);
    $original = $first->getAttribute('password');

    $second = $handler->handleUser(['email' => 'ana@example.com', 'name' => 'Ana Pérez'], $token);

    // Rewriting it on every login would hand every account the same known
    // password, so this has to stay untouched.
    expect($second->getKey())->toBe($first->getKey());
    expect($second->getAttribute('password'))->toBe($original);
    expect($second->getAttribute('name'))->toBe('Ana Pérez');
    expect(TestUser::whereEmail('ana@example.com')->count())->toBe(1);

    Mockery::close();
});

/** @var TestCase $this */
it('gives every new user a different password', function () {
    $handler = new BaseOAuthUserHandler();
    $token   = Mockery::mock(AccessToken::class);

    $una = $handler->handleUser(['email' => 'una@example.com', 'name' => 'Una'], $token);
    $dos = $handler->handleUser(['email' => 'dos@example.com', 'name' => 'Dos'], $token);

    expect($dos->getAttribute('password'))->not->toBe($una->getAttribute('password'));

    Mockery::close();
});

/** @var TestCase $this */
it('raises a catchable failure when the provider returns no email', function () {
    $handler = new BaseOAuthUserHandler();

    // IdentityProviderException is what the controller already catches, so the
    // user is redirected to the login route instead of meeting a 500.
    expect(fn () => $handler->handleUser(['sub' => 'sub-123'], Mockery::mock(AccessToken::class)))
        ->toThrow(IdentityProviderException::class);

    expect(TestUser::count())->toBe(0);

    Mockery::close();
});

/** @var TestCase $this */
it('falls back to the email when the provider returns no name', function () {
    $handler = new BaseOAuthUserHandler();

    $user = $handler->handleUser(['email' => 'sinnombre@example.com'], Mockery::mock(AccessToken::class));

    expect($user->getAttribute('name'))->toBe('sinnombre@example.com');

    Mockery::close();
});
