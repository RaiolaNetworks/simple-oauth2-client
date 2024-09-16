<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Orchestra\Testbench\package_path;

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

        // Publish the config file
        $this->call('vendor:publish', [
            '--tag' => 'oauth-config',
            '--force',
        ]);
        info('The configuration file has been published.');

        // Load migrations in migrations queue and run
        app()->make('oauth')->loadMigrations();
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
            label: 'Login route:',
            placeholder: 'E.g. /login',
            default: '/login',
        );

        config()->set('oauth.user_model_name', $modelName);
        config()->set('oauth.guard_name', $guardName);
        config()->set('oauth.login_route', $loginRoute);
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

        if(app()->environment() === 'testing') {
            $path                 = 'Tests/Models/TestUser.php';
        }

        return match (true) {
            file_exists($path) === false                                              => 'Incorrect model path.',
            class_implements($class) === false                                        => 'This model is not allowed.',
            ! in_array($authenticatableClass, array_values(class_implements($class))) => 'This model not implement the Authenticatable interface.',
            default                                                                   => null,
        };
    }
}
