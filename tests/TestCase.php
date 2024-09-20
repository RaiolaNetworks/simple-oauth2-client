<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
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

        $this->declareTestRoutes();
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

        config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    protected function declareTestRoutes(): void
    {
        Route::get('/login', function () {
            return 'Login Page';
        })->name(config('oauth.login_route_name'));

        Route::get('/', function () {
            return 'Home Page';
        })->name(config('oauth.redirect_route_name_callback_ok'));
    }
}
