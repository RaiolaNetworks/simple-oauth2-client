<?php

declare(strict_types=1);

use Illuminate\Console\Command;
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
