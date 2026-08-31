<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth;

use Illuminate\Contracts\Foundation\Application;
use Raiolanetworks\OAuth\Commands\OAuthCommand;
use Raiolanetworks\OAuth\Commands\OAuthEncryptTokensCommand;
use Raiolanetworks\OAuth\Contracts\OAuthGroupHandlerInterface;
use Raiolanetworks\OAuth\Contracts\OAuthUserHandlerInterface;
use Raiolanetworks\OAuth\Handlers\BaseOAuthGroupHandler;
use Raiolanetworks\OAuth\Handlers\BaseOAuthUserHandler;
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
    }

    /**
     * Runs after the package configuration has been merged, so the handler
     * classes can be read from it.
     */
    public function packageRegistered(): void
    {
        $this->bindUserGroupHandlers();
    }

    /**
     * Bind user and group handlers, resolving the configured classes on demand.
     */
    public function bindUserGroupHandlers(): void
    {
        $this->app->bind(OAuthUserHandlerInterface::class, function (Application $app): OAuthUserHandlerInterface {
            /** @var class-string<OAuthUserHandlerInterface> $userHandler */
            $userHandler = config('oauth.user_handler') ?? BaseOAuthUserHandler::class;

            return $app->make($userHandler);
        });

        $this->app->bind(OAuthGroupHandlerInterface::class, function (Application $app): OAuthGroupHandlerInterface {
            /** @var class-string<OAuthGroupHandlerInterface> $groupHandler */
            $groupHandler = config('oauth.group_handler') ?? BaseOAuthGroupHandler::class;

            return $app->make($groupHandler);
        });
    }
}
