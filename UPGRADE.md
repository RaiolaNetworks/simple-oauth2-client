# Upgrading from v1.x to v2.x

## 1. Migrate the database and encrypt tokens

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

## 2. Verify Laravel version

v2 requires **Laravel 11 or later**. Laravel 10 is no longer supported (it reached EOL in February 2025).

If you are still on Laravel 10, upgrade to Laravel 11+ before updating this package.

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

- **Constructor signature changed** in `OAuthController`: nullable parameters with service locator fallback have been replaced with required type-hinted dependencies. If you extended this controller, update your constructor.
- **Rate limiting**: OAuth routes now include `throttle:5,1` middleware. Override the routes if you need a different limit.
- **Default `user_model_name`**: Config default changed from `Raiolanetworks\OAuth\Tests\Models\TestUser` to `App\Models\User`. This only affects new installations.
