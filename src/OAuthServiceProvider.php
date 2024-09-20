<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth;

use Raiolanetworks\OAuth\Commands\OAuthCommand;
use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OAuthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('oauth')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasTranslations()
            ->hasMigration('modify_user_table')
            ->hasCommand(OAuthCommand::class);

        // Register the main class to use with the facade
        $this->app->singleton('oauth', fn () => $this);

        $this->bindUserGroupHandlers();
    }

    /**
     * Method to load the migrations when php migrate is run in the console.
     */
    public function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Bind user and group handlers.
     */
    public function bindUserGroupHandlers(): void
    {
        /** @var string $userHandler */
        $userHandler = config('oauth.user_handler');

        /** @var string $groupHandler */
        $groupHandler = config('oauth.group_handler');

        $this->app->bind(OAuthUserHandlerInterface::class, $userHandler);
        $this->app->bind(OAuthGroupHandlerInterface::class, $groupHandler);
    }
}
