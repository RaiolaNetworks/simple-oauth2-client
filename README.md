# Easily add OAuth2 access to your projects

[![Latest Version on Packagist](https://img.shields.io/packagist/v/raiolanetworks/oauth.svg?style=flat-square)](https://packagist.org/packages/raiolanetworks/simple-oauth2-client)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/raiolanetworks/oauth/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/raiolanetworks/oauth/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/raiolanetworks/oauth/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/raiolanetworks/oauth/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/raiolanetworks/oauth.svg?style=flat-square)](https://packagist.org/packages/raiolanetworks/simple-oauth2-client)

This OAuth for Laravel package provides a simple and reusable integration for implementing OAuth authentication in Laravel projects. The main goal is to allow developers to extend and customize their application's authentication system without needing to modify their existing user models.

The package is designed to work flexibly with any user model that implements the Authenticatable interface, ensuring that it can be easily adapted to various projects without direct dependencies on a specific user model.

## Get to know us

[<img src="https://cdn-assets.raiolanetworks.com/dist/images/logos/logo-blue.svg" width="419px" />](https://raiolanetworks.com)

## Requirements

- PHP `8.2`, `8.3`, `8.4` or `8.5`
- Laravel `12.x` or `13.x`

> Using Laravel 11 or older? Stay on the `2.x` release line of this package — Composer will keep you on a compatible version automatically. See [UPGRADE](UPGRADE.md) for the `2.x` → `3.x` migration.

## Installation

You can install the package via composer:

```bash
composer require raiolanetworks/simple-oauth2-client
```

The next step is to configure the package using this command:

```bash
php artisan oauth:install
```

When this command is executed, the user will be guided through a series of steps to properly configure the necessary variables in both the configuration file and the environment file.

#### Steps in the installation process:

### Setting variables in the configuration file
- **Authenticatable model name**: Here you need to enter the name of the user management model used in the project, which must implement the `Authenticatable` interface.

- **Main guard name**: You should specify the name of the guard that handles the login process in the project.

- **Login route**: You need to provide the route defined in the project where the login process takes place.

- **Route name when callback is OK**: Here you indicate the name of your project path where the redirection will be made after a correct response from the provider.

- **Will you use the refresh token system in your app?**: Checking 'Yes' will allow the 'offline_access' scope to be added to the provider configuration. Which will allow the use of refresh token (as long as it is enabled in your OAuth provider).

### Other configuration values
- **`preserve_intended_url`**: After a successful callback the user is sent back to the URL they were trying to reach before being asked to authenticate, so a shared deep link survives the login. When no such URL was stored, they land on `redirect_route_name_callback_ok` as usual. Set it to `false` to always end at that route.

### Creation of variables in the .env file
- **OAuth base URL**: Enter the base URL of the OAuth provider, which will be used for authorization and authentication requests.

- **OAuth client ID**: Provide the unique identifier of the OAuth client, issued by the OAuth authentication service being used.

- **OAuth client secret key**: Enter the secret key associated with the OAuth client, which is used to validate the authentication between the client and the OAuth server.

- **OAuth admin group name**: Specify the name of the user group with administrative privileges that will be managed within the OAuth system.

- **OAuth mode**: Select the mode of operation of the OAuth system, which will allow 3 modes: “OAUTH”, “PASSWORD” or “BOTH”.

- **OAuth route prefix**: Path prefix for the package routes, `oauth` by default, which serves them at `/oauth/request` and `/oauth/callback`. Set `OAUTH_ROUTE_PREFIX` to serve them elsewhere, for example `auth`. The redirect URI announced to the provider is `APP_URL` plus the callback route path, so it follows the prefix and the two can never disagree. Register that URI in your provider. It is rooted in `APP_URL` on purpose, never in the incoming request, so a reverse proxy terminating TLS, a spoofed `Host` header or serving the app under several hostnames cannot change what the provider is asked to match.

  `OAUTH_CALLBACK_URI` is deprecated and no longer read. It used to build the redirect URI while the route itself was fixed at `/oauth/callback`, so any other value produced a 404 after authenticating.

## IMPORTANT

~~~
If the process is not completed correctly or is aborted, the implementation and use of the package will result in errors, such as:

- Missing the new database table required to store OAuth user data.
- Incorrectly configured configuration file.
- Environment variables that are improperly defined or missing.
~~~

Once all steps are completed, the migrations will be automatically executed and the configuration file will be published.

You can publish different files:

#### Migrations
```bash
php artisan vendor:publish --tag="oauth-migrations"
```

#### Config file
```bash
php artisan vendor:publish --tag="oauth-config"
```

#### Translations
```bash
php artisan vendor:publish --tag="oauth-translations"
```

## Implementing the Package in the Project


Before developing your workflow, it is worth understanding how the package creates and updates users and groups, because that is the part you are most likely to replace.

Two contracts drive it: [OAuthUserHandlerInterface](src/Contracts/OAuthUserHandlerInterface.php), with a `handleUser()` method, and [OAuthGroupHandlerInterface](src/Contracts/OAuthGroupHandlerInterface.php), with a `handleGroups()` method. Point the `user_handler` and `group_handler` keys of the [configuration file](config/oauth.php) at the classes that implement them:

```php
return [
    ...

    'user_handler'  => App\OAuth\UserHandler::class,
    'group_handler' => App\OAuth\GroupHandler::class,
];
```

Any class the container can build will do, and a dedicated class per handler is the easiest thing to read and to test. Implementing the contracts on your `User` model also works, and earlier versions of this README suggested it, but it puts authentication logic on the model that logic is about.

### Do you need to write your own?

**`handleGroups()`: yes, if groups matter to you.** [BaseOAuthGroupHandler](src/Handlers/BaseOAuthGroupHandler.php) does nothing at all. It exists so the package runs before you have decided how the provider's groups map onto your roles or permissions.

**`handleUser()`: expect to, eventually.** [BaseOAuthUserHandler](src/Handlers/BaseOAuthUserHandler.php) is deliberately minimal. It exists so that a fresh install can log in before you have written anything, and it knows only the columns Laravel's own `users` table ships with (`name`, `email`, `password`). It is a starting point, not something to ship on: write your own as soon as you have columns of your own to fill, a different rule for matching accounts, or anything at all to do beyond creating the user.

### What the default handler does

Four things, and it fits on a screen — read [the class](src/Handlers/BaseOAuthUserHandler.php) rather than trusting this list:

- **Matches the account by email address**, with `firstOrNew`, creating one when there is none.
- **Gives new users a random password** they cannot authenticate with, because Laravel's `users` table declares the column `NOT NULL` and the first login would otherwise die on it. Written on creation only. Projects that dropped the column are left alone.
- **Sets `name`** from the `name` claim, falling back to the email address when the provider sends none.
- **Raises `IdentityProviderException`** when the provider sends no email address at all. The controller already catches it, so the user is returned to your login route with a message instead of meeting a 500.

That is the whole of it. It does not read `email_verified`, never touches `email_verified_at`, and matches on the email address rather than on `sub` — all decisions it leaves to you, for the reasons below.

### Writing your own: what the contract expects

`handleUser()` receives the provider's claims and the access token, and has to return the Eloquent model for the authenticated user. Four things are worth knowing before you write one, because getting them wrong is easy and the consequences are quiet:

1. **Return a saved model.** The controller reads its primary key straight afterwards to link the `oauth` row, so an unsaved instance will not do.
2. **If your `users` table requires a password, set one on creation only.** Writing a password on every login leaves every account in your application sharing the same value, and if a password login form is reachable anywhere, that value is a key to all of them. `Str::random()` per user, never a literal.
3. **Choose deliberately what you match accounts on.** The email address is convenient, but your provider controls it, so matching on it means any identity that can present a given address inherits the local account carrying it, including accounts created before you adopted OAuth. It has a quieter cost too: the controller links the identity with `OAuth::updateOrCreate()` keyed on the pair `user_id` + `oauth_id`, so when the provider changes someone's address, the next login creates a second user **and** a second `oauth` row instead of updating the ones already there. Matching on `sub` through the `oauth` table avoids both, at the cost of needing a rule for what to do on the first login, when no link exists yet.
4. **Do not assume the claims are there.** `email`, `name` and `groups` all depend on granted scopes and on the provider's claim mapping. Throwing `IdentityProviderException` gets you a graceful redirect to your login route; anything else surfaces as a 500.

The same applies to `handleGroups()`, which receives the provider's group list and the user, and returns nothing. It runs on every login, so it is the right place to revoke a role as well as to grant one.

---

Once you have installed the package in your project, the next step is to configure your own login flow. This can be done through a button, link, or any other interface element that triggers a function in a controller. In this function, you'll implement the package and call the `request()` function:

```php
$authController = new OAuthController;
$authController->request();
```

### 1. Create a Controller to Handle OAuth Authentication

First, you'll need to create a controller that handles the OAuth authentication logic. You can use the `OAuthController` provided by the package or create your own controller. The main goal is to call the `request()` method from the package to start the OAuth authentication process.

##### Example Controller:

```php
<?php

namespace App\Http\Controllers;

use Raiolanetworks\OAuth\OAuthController;

class AuthController extends Controller
{
    public function loginWithOAuth()
    {
        $authController = new OAuthController;
        return $authController->request();
    }
}
```

### 2. Set Up a Login Route

In your routes file (`routes/web.php`), define a route that points to the controller you just created. This route will trigger the OAuth authentication process when the user interacts with the login button or link.

```php
use App\Http\Controllers\AuthController;

Route::get('/login/oauth', [AuthController::class, 'loginWithOAuth'])->name('login.oauth');
```

### 3. Create a Login Button or Link in the View

In your application's view (e.g., `resources/views/auth/login.blade.php`), add a button or link that points to the route you defined in the previous step. When the user clicks the button, the OAuth authentication process will begin.

```html
<a href="{{ route('login.oauth') }}" class="btn btn-primary">
    Log in with OAuth
</a>
```

### 4. Authentication Process

When the user clicks the button or link, a request will be sent to the `login()` function of the `AuthController`. From there, the controller will call the `request()` method of the package's `OAuthController`, which handles the OAuth authentication flow by redirecting the user to the OAuth provider for authorization.

Once the user completes the authorization process, the OAuth provider will redirect back to your application, where you can handle the response and authenticate the user in your system.

This will complete the integration of the OAuth package into your project, allowing you to set up a login flow that triggers the OAuth authentication process with a button or link.

### Other features

This section talks about certain functions of the package, which are good to know.

#### Renew tokens

For token renewal, simply set the 'offline_access' variable in [the configuration file](config/oauth.php) to `true`; the package will handle the rest.

A [middleware](src/Middleware/OAuthTokenRenewal.php) is available that calls the `renew()` function of the `OAuthController`. This function checks whether the authenticated user has an OAuth token and if the expiration time has not passed. If the token has expired, it will determine if a refresh token is being handled. It will either generate a new token or reject and unauthenticate the user's session accordingly.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Martín Gómez](https://github.com/soymgomez)
- [Víctor Escribano](https://github.com/victore13)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
