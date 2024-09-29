<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Raiolanetworks\OAuth\Commands\OAuthCommand;
use Raiolanetworks\OAuth\Tests\Models\TestUser;

it('run the install command', function () {
    $tempFilePath = base_path('.env');
    file_put_contents($tempFilePath, 'OAUTH_BASE_URL="https://test"');

    $this->artisan('oauth:install')
        ->expectsQuestion('Model name Authenticatable:', TestUser::class)
        ->expectsQuestion('Main guard name:', 'web')
        ->expectsQuestion('Login route name:', 'login')
        ->expectsQuestion('Route name when callback is OK:', 'home')
        ->expectsQuestion('Will you use the refresh token system in your app?', 'Yes')
        ->expectsQuestion('Oauth base url:', 'https://asgard.your.company')
        ->expectsQuestion('Oauth client ID:', 'CLIENTID')
        ->expectsQuestion('Oauth client secret key:', 'SECRETKEY')
        ->expectsQuestion('Oauth name admin group:', 'admin_group')
        ->expectsQuestion('OAuth mode. Options: login only with username and password, only with OAuth or both:', 'BOTH')
        ->assertExitCode(Command::SUCCESS);

    unlink($tempFilePath);
});

it('verify that if the configuration file does not exist it returns an exception', function () {
    $configPath = 'config/oauth.php';
    $backupPath = 'config/oauth_backup.php';

    if (file_exists($configPath)) {
        rename($configPath, $backupPath);
    }

    try {
        $command = new OAuthCommand();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('setConfigVariable');
        $method->setAccessible(true);

        expect(fn () => $method->invokeArgs($command, ['some_key', 'some_value']))
            ->toThrow(Exception::class, 'Unable to find the configuration file...');
    } finally {
        if (File::exists($backupPath)) {
            File::move($backupPath, $configPath);
        }
    }
});
