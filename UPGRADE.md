# Upgrading from v2.x to v3.x

v3 is a maintenance release that drops end-of-life platforms and removes previously deprecated code. There are **no behavioural changes** to the OAuth flow.

## 1. Verify platform versions

v3 requires:

- **PHP 8.2 or later** (8.2, 8.3, 8.4, 8.5).
- **Laravel 12 or later**. Laravel 11 is no longer supported (its security support ended on March 12, 2026).

If you are still on Laravel 11, you can safely remain on the `2.x` release line — Composer will not upgrade you to `3.x` until your framework meets the new constraint. Upgrade to Laravel 12+ before requiring `^3.0`.

## 2. Remove the deprecated event alias

The `EventsOAuthTokenUpdated` class (deprecated in v2) has been removed. Use `OAuthTokenUpdated` instead:

```php
// Before (removed in v3)
use Raiolanetworks\OAuth\Events\EventsOAuthTokenUpdated;

// After
use Raiolanetworks\OAuth\Events\OAuthTokenUpdated;
```

No database migration or token re-encryption is required for the v2 → v3 upgrade.

> **Upgrading directly from v1.x?** The two steps above are not enough on their own. You must **also complete every step in the "Upgrading from v1.x to v2.x" section below** — in particular the database migration and token encryption. Skipping the v2 release does **not** skip its schema changes: without them the first login after the upgrade fails with an error such as:
>
> ```
> SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'oauth_refresh_token' at row 1
> ```
>
> This happens because v2 stores tokens encrypted, and encrypted values no longer fit in the original `string(255)` column.

---

# Upgrading from v1.x to v2.x

## 1. Verify Laravel version

v2 requires **Laravel 11 or later**. Laravel 10 is no longer supported (it reached EOL in February 2025).

If you are still on Laravel 10, upgrade to Laravel 11+ before updating this package.

## 2. Migrate the database and encrypt tokens

v2 encrypts OAuth tokens at rest. **You must run the migration and encrypt existing tokens before any request hits the new code**, otherwise plaintext tokens will fail to decrypt.

Put your application in maintenance mode, then run all steps together:

```bash
php artisan down
php artisan vendor:publish --tag=oauth-migrations
php artisan migrate
php artisan oauth:encrypt-tokens
php artisan up
```

The migration will:
- Change `oauth_refresh_token` from `string(255)` to `longText` (required for encrypted values).
- Change `oauth_token_expires_at` from `integer` to `unsignedBigInteger` (fixes the 2038 problem).
- Add an index on `oauth_id`.

The `oauth:encrypt-tokens` command will:
- Find all OAuth records with plaintext tokens.
- Skip records that are already encrypted.
- Encrypt tokens in place.

> **Important:** Ensure your `APP_KEY` is set before running this command. The same key must be used to decrypt the tokens later. The command is safe to re-run — it will not double-encrypt already encrypted values.

## 3. Update event listeners

The event class `EventsOAuthTokenUpdated` has been renamed to `OAuthTokenUpdated`.

A deprecated alias is provided for backwards compatibility, so existing listeners will continue to work. However, you should update your references:

```php
// Before
use Raiolanetworks\OAuth\Events\EventsOAuthTokenUpdated;

// After
use Raiolanetworks\OAuth\Events\OAuthTokenUpdated;
```

The alias `EventsOAuthTokenUpdated` will be removed in v3.

## 4. Update facade usage (if applicable)

The `src/OAuth.php` class has been removed. If you referenced `\Raiolanetworks\OAuth\OAuth` directly, use the facade instead:

```php
use Raiolanetworks\OAuth\Facades\OAuth;
```

The facade now resolves to `OAuthService` via the `'oauth'` container key.

## 5. Other breaking changes

- **Constructor signature changed** in `OAuthController`: nullable parameters with service locator fallback have been replaced with required type-hinted dependencies. Resolve the controller through the service container so its dependencies are injected — do **not** instantiate it manually:

  ```php
  // Before (breaks in v3: "Too few arguments to function ...OAuthController::__construct(), 0 passed")
  $controller = new OAuthController();
  $controller->request();

  // After — the container injects the dependencies, and request() returns the redirect, so return it
  return app(OAuthController::class)->request();
  ```

  If you extended this controller, update your constructor accordingly.
- **Rate limiting**: OAuth routes now include `throttle:5,1` middleware. Override the routes if you need a different limit.
- **Default `user_model_name`**: Config default changed from `Raiolanetworks\OAuth\Tests\Models\TestUser` to `App\Models\User`. This only affects new installations.
