<?php

declare(strict_types=1);

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Raiolanetworks\OAuth\Handlers\BaseOAuthUserHandler;
use Raiolanetworks\OAuth\Models\OAuth;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

/**
 * @param array<string, mixed> $claims
 */
function resolveUser(array $claims): TestUser
{
    /** @var TestUser $user */
    $user = (new BaseOAuthUserHandler())->handleUser($claims, Mockery::mock(AccessToken::class));

    return $user;
}

function linkAccount(TestUser $user, string $subject): void
{
    OAuth::factory(state: ['user_id' => $user->id, 'oauth_id' => $subject])->create();
}

it('creates an account for a subject and email never seen before', function () {
    $user = resolveUser(['sub' => 'sub-1', 'email' => 'New@Example.com', 'name' => 'New User']);

    expect($user->exists)->toBeTrue()
        ->and($user->email)->toBe('new@example.com')
        ->and($user->name)->toBe('New User');
});

it('identifies a linked account by subject even when the email changed', function () {
    $user = TestUser::factory()->create(['email' => 'old@example.com']);
    linkAccount($user, 'sub-1');

    $resolved = resolveUser(['sub' => 'sub-1', 'email' => 'new@example.com', 'email_verified' => true, 'name' => 'Renamed']);

    expect($resolved->id)->toBe($user->id)
        ->and($resolved->email)->toBe('new@example.com')
        ->and($resolved->name)->toBe('Renamed');
});

it('keeps the stored email when the new one is not verified', function () {
    $user = TestUser::factory()->create(['email' => 'old@example.com']);
    linkAccount($user, 'sub-1');

    expect(resolveUser(['sub' => 'sub-1', 'email' => 'new@example.com', 'name' => 'x'])->email)->toBe('old@example.com');
});

it('does not take an email that already belongs to another account', function () {
    $user = TestUser::factory()->create(['email' => 'mine@example.com']);
    linkAccount($user, 'sub-1');
    TestUser::factory()->create(['email' => 'victim@example.com']);

    expect(resolveUser(['sub' => 'sub-1', 'email' => 'victim@example.com', 'email_verified' => true])->email)
        ->toBe('mine@example.com');
});

it('links a new subject to an existing account when the email is verified', function () {
    $user = TestUser::factory()->create(['email' => 'person@example.com']);

    expect(resolveUser(['sub' => 'sub-1', 'email' => 'PERSON@example.com', 'email_verified' => true])->id)->toBe($user->id);
});

it('refuses to link a new subject to an existing account with an unverified email', function () {
    TestUser::factory()->create(['email' => 'victim@example.com']);

    resolveUser(['sub' => 'attacker', 'email' => 'victim@example.com', 'email_verified' => false]);
})->throws(IdentityProviderException::class);

it('refuses to link an account already linked to another subject', function () {
    $victim = TestUser::factory()->create(['email' => 'victim@example.com']);
    linkAccount($victim, 'victim-sub');

    resolveUser(['sub' => 'attacker', 'email' => 'victim@example.com', 'email_verified' => true]);
})->throws(IdentityProviderException::class);

it('rejects a subject linked to more than one account', function () {
    linkAccount(TestUser::factory()->create(), 'sub-1');
    linkAccount(TestUser::factory()->create(), 'sub-1');

    resolveUser(['sub' => 'sub-1', 'email' => 'a@example.com']);
})->throws(IdentityProviderException::class);

it('rejects a missing subject or an invalid email', function (array $claims) {
    TestUser::factory()->create(['email' => '']);

    resolveUser($claims);
})->throws(IdentityProviderException::class)->with([
    'no subject'    => [['email' => 'a@example.com']],
    'empty subject' => [['sub' => ' ', 'email' => 'a@example.com']],
    'no email'      => [['sub' => 'sub-1']],
    'empty email'   => [['sub' => 'sub-1', 'email' => '']],
    'null email'    => [['sub' => 'sub-1', 'email' => null]],
    'not an email'  => [['sub' => 'sub-1', 'email' => 'nope']],
]);

it('falls back to the username and then the email for the name', function () {
    expect(resolveUser(['sub' => 's1', 'email' => 'a@example.com', 'preferred_username' => 'alice'])->name)->toBe('alice')
        ->and(resolveUser(['sub' => 's2', 'email' => 'b@example.com'])->name)->toBe('b@example.com');
});

it('creates accounts with an unverified email by default', function () {
    expect(resolveUser(['sub' => 'sub-1', 'email' => 'a@example.com', 'email_verified' => false])->exists)->toBeTrue();
});

it('rejects an unverified email when verified emails are required', function (array $claims) {
    config()->set('oauth.require_verified_email', true);

    resolveUser($claims);
})->throws(IdentityProviderException::class)->with([
    'explicitly unverified' => [['sub' => 'sub-1', 'email' => 'a@example.com', 'email_verified' => false]],
    'claim missing'         => [['sub' => 'sub-1', 'email' => 'a@example.com']],
    'truthy but not true'   => [['sub' => 'sub-1', 'email' => 'a@example.com', 'email_verified' => 'true']],
]);

it('accepts a verified email when verified emails are required', function () {
    config()->set('oauth.require_verified_email', true);

    expect(resolveUser(['sub' => 'sub-1', 'email' => 'a@example.com', 'email_verified' => true])->exists)->toBeTrue();
});
