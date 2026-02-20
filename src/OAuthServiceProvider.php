<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth;

use Raiolanetworks\OAuth\Commands\OAuthCommand;
use Raiolanetworks\OAuth\Commands\OAuthEncryptTokensCommand;
use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Services\OAuthService;
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
            ->hasMigrations(['create_oauth_table', 'upgrade_oauth_table_v2'])
            ->hasCommands([OAuthCommand::class, OAuthEncryptTokensCommand::class]);

        // Register the main class to use with the facade
        $this->app->singleton('oauth', fn ($app) => $app->make(OAuthService::class));

        $this->bindUserGroupHandlers();
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
