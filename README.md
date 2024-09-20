# Easily add OAuth2 access to your projects

[![Latest Version on Packagist](https://img.shields.io/packagist/v/raiolanetworks/oauth.svg?style=flat-square)](https://packagist.org/packages/raiolanetworks/oauth)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/raiolanetworks/oauth/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/raiolanetworks/oauth/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/raiolanetworks/oauth/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/raiolanetworks/oauth/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/raiolanetworks/oauth.svg?style=flat-square)](https://packagist.org/packages/raiolanetworks/oauth)

This OAuth for Laravel package provides a simple and reusable integration for implementing OAuth authentication in Laravel projects. The main goal is to allow developers to extend and customize their application's authentication system without needing to modify their existing user models.

The package is designed to work flexibly with any user model that implements the Authenticatable interface, ensuring that it can be easily adapted to various projects without direct dependencies on a specific user model.

## Installation

You can install the package via composer:

```bash
composer require raiolanetworks/oauth
```

The next step is to configure the package using this command:

```bash
php artisan oauth:install
```

When this command is executed, the user will be guided through a series of steps to properly configure the necessary variables in both the configuration file and the environment file.

Steps in the installation process:

### Setting variables in the configuration file
- **Authenticatable model name**: Here you need to enter the name of the user management model used in the project, which must implement the `Authenticatable` interface.

- **Main guard name**: You should specify the name of the guard that handles the login process in the project.

- **Login route**: You need to provide the route defined in the project where the login process takes place.

### Creation of variables in the .env file
- **OAuth base URL**: Enter the base URL of the OAuth provider, which will be used for authorization and authentication requests.

- **OAuth client ID**: Provide the unique identifier of the OAuth client, issued by the OAuth authentication service being used.

- **OAuth client secret key**: Enter the secret key associated with the OAuth client, which is used to validate the authentication between the client and the OAuth server.

- **OAuth admin group name**: Specify the name of the user group with administrative privileges that will be managed within the OAuth system.

- **OAuth mode**: Select the mode of operation of the OAuth system, which will allow 3 modes: “OAUTH”, “PASSWORD” or “BOTH”.

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

Before starting to develop the workflow, it is recommended to understand how the package works when creating or modifying users and groups.

To achieve this, two interfaces have been created: [OAuthUserHandlerInterface](src/Contracts/OAuthUserHandlerInterface.php) and [OAuthGroupHandlerInterface](src/Contracts/OAuthGroupHandlerInterface.php). These interfaces can be implemented in the user model of your application, allowing you to override the `handleUser()` and `handleGroup()` methods, respectively.

There are also two predefined classes: [BaseOAuthUserHandler](src/Handlers/BaseOAuthUserHandler.php) and [BaseOAuthGroupHandler](src/Handlers/BaseOAuthGroupHandler.php), which implement these interfaces with default logic. These will serve as an example for the developer and will also help, if it is a simple application, for the package to work without having to overwrite anything.

**IMPORTANT**

It is likely necessary to implement these interfaces to override the logic for handling the users and groups returned by the OAuth service.

However, **do not forget** to override the `user_handler` and `group_handler` variables in the [configuration file](config/oauth.php), specifying which model will override the interface methods.

```php
return [
    ...

    'user_handler'  => App\Models\User::class,
    'group_handler' => App\Models\User::class,
];
```

---

Once you have installed the package in your project, the next step is to configure your own login flow. This can be done through a button, link, or any other interface element that triggers a function in a controller. In this function, you'll implement the package and call the `request()` function:

```php
$authController = new OAuthController;
$authController->request();
```

#### 1. Create a Controller to Handle OAuth Authentication

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

#### 2. Set Up a Login Route

In your routes file (`routes/web.php`), define a route that points to the controller you just created. This route will trigger the OAuth authentication process when the user interacts with the login button or link.

```php
use App\Http\Controllers\AuthController;

Route::get('/login/oauth', [AuthController::class, 'loginWithOAuth'])->name('login.oauth');
```

#### 3. Create a Login Button or Link in the View

In your application's view (e.g., `resources/views/auth/login.blade.php`), add a button or link that points to the route you defined in the previous step. When the user clicks the button, the OAuth authentication process will begin.

```html
<a href="{{ route('login.oauth') }}" class="btn btn-primary">
    Log in with OAuth
</a>
```

#### 4. Authentication Process

When the user clicks the button or link, a request will be sent to the `login()` function of the `AuthController`. From there, the controller will call the `request()` method of the package's `OAuthController`, which handles the OAuth authentication flow by redirecting the user to the OAuth provider for authorization.

Once the user completes the authorization process, the OAuth provider will redirect back to your application, where you can handle the response and authenticate the user in your system.

This will complete the integration of the OAuth package into your project, allowing you to set up a login flow that triggers the OAuth authentication process with a button or link.

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
