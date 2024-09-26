<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Tests\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Raiolanetworks\OAuth\Tests\Database\Factories\TestUserFactory;

class TestUser extends Model implements Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
    ];

    protected static function newFactory(): Factory
    {
        return TestUserFactory::new();
    }

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
