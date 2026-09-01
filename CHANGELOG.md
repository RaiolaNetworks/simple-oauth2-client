# Changelog

All notable changes to `oauth` will be documented in this file.

## v3.2.0 - 2026-09-01

### ➕ Added
- `route_prefix` (`OAUTH_ROUTE_PREFIX`, `oauth` by default) sets the path prefix for the package routes, so the callback can be served at `/auth/callback` instead of `/oauth/callback`. The route names stay `oauth.request` and `oauth.callback`, so `route()` calls in your application keep working. `oauth:install` now asks for the prefix and prints the redirect URI to register in your provider.
- `preserve_intended_url` (`true` by default) sends the user back to the URL they were trying to reach before being asked to authenticate, so a shared deep link survives the login. With no stored URL they land on `redirect_route_name_callback_ok` as before. Set it to `false` to always end at that route.

### 🐛 Fixed
- The redirect URI announced to the provider is now `APP_URL` plus the callback route path. It used to concatenate `APP_URL` with `OAUTH_CALLBACK_URI`, a separate setting that defaulted to an empty string and was decoupled from the route: publishing the config without running `oauth:install` advertised the bare domain, and any value other than `/oauth/callback` produced a 404 *after* the user had already authenticated. Deriving the path from the route means the two can no longer disagree.
- The redirect URI is rooted in `APP_URL`, never in the incoming request. Building it from the request would advertise `http://` behind a reverse proxy terminating TLS (`TrustProxies` is not enabled by default in Laravel 11+), follow a spoofed `Host` header, and ignore `APP_URL` entirely on a local server — each of them a URI the provider has not registered, which fails the login.

### ⚠️ Deprecated
- `OAUTH_CALLBACK_URI` (`oauth.callback`) is no longer read. The key stays in the config file so already published configs keep working, but it has no effect. Use `OAUTH_ROUTE_PREFIX` to move the callback.

  **Migration:** only one setup needs action — an application that declared its own routes against `OAuthController` to serve the callback elsewhere and set `OAUTH_CALLBACK_URI` to match. The advertised URI now follows the package route, so either set `OAUTH_ROUTE_PREFIX` to that same prefix or register the new URI in your provider.

## v3.1.1 - 2026-08-31

### 🐛 Fixed
- Resolve the user and group handlers after the package config is merged. Binding them from `configurePackage()` read a null class name and handed the container `null` as the concrete class, so on a fresh install — before `config/oauth.php` was published — every route failed with `Target [OAuthUserHandlerInterface] is not instantiable`, `php artisan route:list` included. The bindings now run in `packageRegistered()` and resolve the configured class through a closure.
- Preserve the stored refresh token when the provider does not return a new one. RFC 6749 §5.1 makes `refresh_token` optional in a refresh response, and `league/oauth2-client` leaves `getRefreshToken()` as `null` in that case, which was overwriting the valid stored token and left the account unable to renew.
- Log out and redirect to the login route when no refresh token is stored, instead of calling the grant with `['refresh_token' => null]`.
- Catch `BadMethodCallException` during renewal. It is what `league/oauth2-client` throws for a null refresh token, and it surfaced as an uncaught 500 on every request until the session was cleared.
- Apply a 60 second safety margin (`TokenRenewalService::EXPIRY_MARGIN_SECONDS`) to every expiry check, so a token expiring mid-request is renewed before the provider rejects it.

### ➕ Added / Changed
- Documented the direct v1 → v3 upgrade path and controller injection in UPGRADE.md.
- Added `@property` annotations to the `OAuth` model, which removed the four remaining `@phpstan-ignore` comments in the package.

## v3.1.0 - 2026-07-16

### ➕ Added
- Localized the callback error and session-expiry messages (`en`, `es`) through reusable translation keys, instead of hard-coding them in English.

### 🐛 Fixed
- Validate the provider identity and PKCE in the OAuth callback. A missing `sub` or PKCE code now raises `IdentityProviderException`, so it is caught and redirected gracefully instead of surfacing a 500.

### 🔧 Changed
- Extracted token renewal into a dedicated `TokenRenewalService`, so the middleware no longer resolves a full controller on every request. A session-cached expiry timestamp lets it skip the database lookup while the token is still valid. `OAuthController::renew()` now delegates to the service and is deprecated.
- Refined the handler contract type hints and simplified `BaseOAuthUserHandler`.
- Require `larastan ^3.0`: 2.x targets PHPStan 1.x and was unsatisfiable against the Laravel 12/13 + PHPStan 2 dev stack.
- Backfilled the CHANGELOG for v2 and v3 and dropped the failing changelog workflow.

## v3.0.0 - 2026-07-16

### 🔒 Security
- Require Laravel `^12.60|^13.10`, excluding versions affected by CRLF injection in the default email rule (GHSA-5vg9-5847-vvmq / CVE-2026-48019, High severity).

### 💥 Breaking changes
- Dropped Laravel 11 support (end-of-life, security support ended 2026-03-12). Laravel 11 users are kept on the `2.x` line automatically by Composer.
- Removed the deprecated `EventsOAuthTokenUpdated` event alias. Use `OAuthTokenUpdated` instead.

### ➕ Added / Changed
- Added PHP 8.5 support (`^8.2|^8.3|^8.4|^8.5`).
- Simplified `laravel/prompts` (`^0.3`) and dev `orchestra/testbench` (`^10.0|^11.0`) constraints.
- Updated the CI matrix, the README (new Requirements section) and UPGRADE.md (v2 → v3 guide).

## v2.0.0 - 2026-03-19

### Security
- Regenerate session before login to prevent session fixation attacks
- Encrypt OAuth tokens at rest via Laravel's encrypted casts

### Fixed
- Middleware now returns the redirect from token renewal instead of silently discarding it
- Fix facade accessor, singleton binding, and remove dead code
- Use `unsignedBigInteger` for token expiry and `longText` for encrypted refresh tokens
- Sanitize regex input with `preg_quote` in env variable replacement

### Added
- Laravel 13 and PHP 8.4 support
- `oauth:encrypt-tokens` command to migrate plaintext tokens to encrypted
- v1 → v2 upgrade migration and `UPGRADE.md` guide

### Changed
- Rate limiting (`throttle:5,1`) on OAuth routes
- Request injection instead of facade
- Rename `EventsOAuthTokenUpdated` → `OAuthTokenUpdated` (deprecated alias kept)
- Constructor property promotion and code cleanup

### Removed (breaking)
- Laravel 10 support (EOL Feb 2025)
- `src/OAuth.php` removed — use `OAuthService` via facade or container

## v1.0.8 - 2025-03-26

### Updated composer to allow deployments with Laravel 12.

## v1.0.7 - 2025-03-17

### Unnecessary dependencies were removed.

Removed Livewire dependency.

## v1.0.6 - 2024-10-22

### Temporary modifications to the configuration file variables.

A line has been added to solve a synchronization problem when recovering a value from the configuration file when executing migrations.

The variables in the configuration file are temporarily configured before finishing the process since the 'oauth' file is not required until the installation command process finishes.

## v1.0.5 - 2024-10-22

### Modified composer.json file.

Added v10.* in the "laravel/framework" requirement to support older versions.

## v1.0.4 - 2024-10-03

### All login modes have been moved to an Enum file.

An Enum file has been created to store all login modes to facilitate access to these modes in the application implementing the package and thus avoid the hardcoding or definitions of the modes.

## v1.0.2 - 2024-10-02

### Checking if OAuth user data exists.

Added a check if OAuth information exists in the OAuthController refresh function.

## Fixed an error in modifying variables in the configuration file - 2024-09-30

A new function has been added to the package installation command class that is responsible for modifying the value of the configuration file variables.

With this, the test responsible for testing said function has been created.

## Improved installation command performance - 2024-09-29

A new function has been added to the package installation command class that is responsible for modifying the value of the configuration file variables.

With this, the test responsible for testing said function has been created.
