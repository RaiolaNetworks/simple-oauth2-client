<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Tests\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class TestUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = TestUser::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                   => fake()->name(),
            'email'                  => fake()->unique()->safeEmail(),
            'password'               => static::$password ??= Hash::make('password'),
            'oauth_id'               => 'oauth_id',
            'oauth_token'            => 'oauth_token',
            'oauth_refresh_token'    => 'oauth_refresh_token',
            'oauth_token_expires_at' => Carbon::now()->addHours(2),
        ];
    }
}
