<?php

declare(strict_types=1);

return [
    /**
     * Environment variables to configuration the OAuth services.
     *
     * base_url:        URL of the chosen system for login via OAuth
     * client_id:       Client ID of the OAuth system
     * client_secret:   Client secret key of the OAuth system
     * callback:        Deprecated and unused. The redirect URI is derived from the
     *                  "oauth.callback" route, so use route_prefix to move it.
     * admin_group:     Name of administration group in the OAuth system
     * mode:            Preferred login mode in your project. Allow 3 types:
     *  PASSWORD -> Show login with username and password
     *  OAUTH -> Show login with oauth
     *  BOTH -> Show both login types
     */
    'base_url'                        => env('OAUTH_BASE_URL', ''),
    'client_id'                       => env('OAUTH_CLIENT_ID', ''),
    'client_secret'                   => env('OAUTH_CLIENT_SECRET', ''),
    'callback'                        => env('OAUTH_CALLBACK_URI', ''),
    'admin_group'                     => env('OAUTH_ADMIN_GROUP', ''),
    'mode'                            => env('OAUTH_MODE', 'OAUTH'),

    /**
     * Path prefix for the package routes, "oauth.request" and "oauth.callback".
     *
     * The redirect URI announced to the provider is built from the callback
     * route, so changing this moves both the route and the redirect URI.
     */
    'route_prefix'                    => env('OAUTH_ROUTE_PREFIX', 'oauth'),

    /**
     * Integration configuration variables.
     *
     * user_model_name:                  Model name in your project of the Authenticatable users (default: \App\Models\User)
     * guard_name:                       Name of the authentication guard to use
     * login_route_name:                 Route name for the login page
     * redirect_route_name_callback_ok:  Route name to redirect after successful OAuth callback
     */
    'user_model_name'                 => 'App\Models\User',
    'guard_name'                      => 'web',
    'login_route_name'                => 'login',
    'redirect_route_name_callback_ok' => 'home',

    /**
     * Send the user back to the URL they were trying to reach before being
     * asked to authenticate, when one was stored.
     *
     * With false, a successful callback always ends at
     * redirect_route_name_callback_ok.
     */
    'preserve_intended_url'           => true,

    /**
     * Handler classes configuration
     *
     * Here the classes in charge of managing the handler classes of your
     * application are declared.
     *
     * By default, the base classes are defined for managing users and groups.
     * But they can be customized to meet the needs of the project.
     *
     * For example:
     * 'user_handler' => App\Models\User::class,
     * 'group_handler' => App\Models\User::class
     *
     * As long as the interfaces have been implemented in these models.
     */
    'user_handler'                    => Raiolanetworks\OAuth\Handlers\BaseOAuthUserHandler::class,
    'group_handler'                   => Raiolanetworks\OAuth\Handlers\BaseOAuthGroupHandler::class,

    /**
     * Allow refresh tokens in your app
     *
     * If the value is true, it will add the 'offline_access' scope in the OAuth
     * provider configuration.
     */
    'offline_access'                  => true,
];
