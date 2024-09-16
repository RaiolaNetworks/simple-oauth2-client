<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Tests\Models;

use Illuminate\Contracts\Auth\Authenticatable;

class TestUser implements Authenticatable
{
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function getTable(): string
    {
        return 'test_users';
    }
}
