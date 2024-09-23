<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Raiolanetworks\OAuth\Models\OAuth;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Raiolanetworks\OAuth\Models\OAuth>
 */
class OAuthFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<OAuth>
     */
    protected $model = OAuth::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'oauth_id'               => 'oauth_id',
            'oauth_token'            => 'oauth_token',
            'oauth_refresh_token'    => 'oauth_refresh_token',
            'oauth_token_expires_at' => Carbon::now()->addHours(2)->timestamp,
        ];
    }
}
