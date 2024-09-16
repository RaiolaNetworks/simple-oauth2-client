<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;
use Raiolanetworks\OAuth\OAuthServiceProvider;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('oauth.user_model_name', TestUser::class);

        // Test migrations
        $this->loadMigrationsFrom(realpath(__DIR__ . '/database/migrations'));

        // Package migrations
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../database/migrations'));

        $this->artisan('migrate')->run();
    }

    protected function getPackageProviders($app)
    {
        return [
            OAuthServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
