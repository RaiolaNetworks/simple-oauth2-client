<?php

declare(strict_types=1);

return [
    // URL of the chosen system for login via OAuth
    'base_url'                   => env('OAUTH_BASE_URL', ''),
    // Client ID of the OAuth system
    'client_id'                  => env('OAUTH_CLIENT_ID', ''),
    // Client secret key of the OAuth system
    'client_secret'              => env('OAUTH_CLIENT_SECRET', ''),
    // Route of the project receiving the callback
    'callback'                   => env('OAUTH_CALLBACK_URI', ''),
    // Name of administration group in the OAuth system
    'admin_group'                => env('OAUTH_ADMIN_GROUP', ''),

    /**
     * Preferred login mode in your project
     *
     * Allow 3 types:
     * PASSWORD -> Show login with username and password
     * OAUTH -> Show login with oauth
     * BOTH -> Show both login types
     */
    'mode'                       => env('OAUTH_MODE', 'OAUTH'),

    // Model name in your project of the Authenticatable users (default: \App\Models\User)
    'user_model_name'            => '\App\Models\User'::class,
    // Guard name who is in charge of this logging in your project
    'guard_name'                 => 'web',

    // Route to redirect when callback response is Ok
    'login_route'                => '/login',
    // Route to redirect when callback response is Ok
    'redirect_route_callback_ok' => '/',
];
