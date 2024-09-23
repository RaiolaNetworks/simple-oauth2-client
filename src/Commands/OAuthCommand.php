<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class OAuthCommand extends Command
{
    public $signature = 'oauth:install';

    public $description = 'Initialize "OAuth" package by following a few simple steps';

    public function handle(): int
    {
        $this->setConfigVariables();
        info('3 variables have been overwritten in the configuration file “oauth.php”.');

        $this->setEnvironmentVariables();
        info('6 new variables have been created in the environment file “.env”.');

        $this->call('vendor:publish', [
            '--tag' => 'oauth-config',
            '--force',
        ]);
        info('The configuration file has been published.');

        info('Loading migrations...');
        app()->make('oauth')->loadMigrations();

        info('Running migrations...');
        $this->call('migrate');

        info('Migrations have been executed.');

        info('OAuth package configured correctly!');

        return self::SUCCESS;
    }

    protected function setConfigVariables(): void
    {
        $modelName = text(
            label: 'Model name Authenticatable:',
            placeholder: 'E.g. app/Models/User',
            default: 'app/Models/User',
            validate: fn (string $value) => $this->modelNameValidation($value),
        );

        $guardName = text(
            label: 'Main guard name:',
            placeholder: 'E.g. web',
            default: 'web',
        );

        $loginRoute = text(
            label: 'Login route name:',
            placeholder: 'E.g. login',
            default: 'login',
        );

        $redirectCallbackOkRoute = text(
            label: 'Route name when callback is OK:',
            placeholder: 'E.g. home',
            default: 'home',
        );

        $offlineAccessScope = select(
            label: 'Will you use the refresh token system in your app?',
            options: ['Yes', 'No'],
            default: 'Yes',
        );

        config()->set('oauth.user_model_name', $modelName);
        config()->set('oauth.guard_name', $guardName);
        config()->set('oauth.login_route_name', $loginRoute);
        config()->set('oauth.redirect_route_name_callback_ok', $redirectCallbackOkRoute);
        config()->set('oauth.offline_access', $offlineAccessScope === 'Yes' ? true : false);
    }

    protected function setEnvironmentVariables(): void
    {
        $oauthBaseUrl = text(
            label: 'Oauth base url:',
            placeholder: 'E.g. https://asgard.your.company',
            required: true,
        );

        $oauthClientID = text(
            label: 'Oauth client ID:',
            required: true,
        );

        $oauthClientSecret = text(
            label: 'Oauth client secret key:',
            required: true,
        );

        $oauthAdminGroup = text(
            label: 'Oauth name admin group:',
            placeholder: 'E.g. "Admins"',
            default: '',
        );

        $oauthMode = select(
            label: 'OAuth mode. Options: login only with username and password, only with OAuth or both:',
            options: ['OAUTH', 'PASSWORD', 'BOTH'],
            required: true,
        );

        $this->createEnvironmentVariables('OAUTH_BASE_URL', $oauthBaseUrl);
        $this->createEnvironmentVariables('OAUTH_CLIENT_ID', $oauthClientID);
        $this->createEnvironmentVariables('OAUTH_CLIENT_SECRET', $oauthClientSecret);
        $this->createEnvironmentVariables('OAUTH_ADMIN_GROUP', $oauthAdminGroup);
        $this->createEnvironmentVariables('OAUTH_CALLBACK_URI', '/oauth/callback');
        $this->createEnvironmentVariables('OAUTH_MODE', $oauthMode);
    }

    protected function createEnvironmentVariables(string $key, string|int $value): void
    {
        $path = base_path('.env');

        if (file_exists($path)) {
            $env = file_get_contents($path);

            if ($env !== false) {
                /** @var string $env */
                if (strpos($env, "{$key}=") !== false) {
                    $env = preg_replace("/^{$key}=.*/m", "{$key}=\"{$value}\"", $env);
                } else {
                    $env .= "\n{$key}=\"{$value}\"";
                }

                file_put_contents($path, $env);
            }
        }
    }

    protected function modelNameValidation(string $value): ?string
    {
        $path                 = $value . '.php';
        $class                = '\\' . Str::ucfirst(Str::replace('/', '\\', $value));
        $authenticatableClass = 'Illuminate\Contracts\Auth\Authenticatable';

        if (app()->environment() === 'testing') {
            $path = 'tests/Models/TestUser.php';
        }

        return match (true) {
            file_exists($path) === false                                              => 'Incorrect model path.',
            class_implements($class) === false                                        => 'This model is not allowed.',
            ! in_array($authenticatableClass, array_values(class_implements($class))) => 'This model not implement the Authenticatable interface.',
            default                                                                   => null,
        };
    }
}
