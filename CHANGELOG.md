# Changelog

All notable changes to `oauth` will be documented in this file.

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
