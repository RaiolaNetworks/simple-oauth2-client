<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Routes;

use Illuminate\Support\Facades\Route;
use Raiolanetworks\OAuth\Controllers\OAuthController;

Route::middleware('web')->prefix('oauth')->group(function () {
    Route::get('/request', [OAuthController::class, 'request'])->name('oauth.request');
    Route::get('/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});
