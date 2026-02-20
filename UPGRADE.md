# Upgrading from v1.x to v2.x

## 1. Run the new migration

v2 changes the `oauth` table schema. Publish and run the migration:

```bash
php artisan vendor:publish --tag=oauth-migrations
php artisan migrate
```

This will:
- Change `oauth_refresh_token` from `string(255)` to `longText` (required for encrypted values).
- Change `oauth_token_expires_at` from `integer` to `unsignedBigInteger` (fixes the 2038 problem).
- Add an index on `oauth_id`.

## 2. Encrypt existing tokens

v2 encrypts OAuth tokens at rest using Laravel's `encrypted` cast. **Existing plaintext tokens must be migrated** or they will fail to decrypt.

Run the provided command:

```bash
php artisan oauth:encrypt-tokens
```

The command will:
- Find all OAuth records with tokens.
- Skip records that are already encrypted.
- Encrypt plaintext tokens in place.

> **Important:** Ensure your `APP_KEY` is set before running this command. The same key must be used to decrypt the tokens later.

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
