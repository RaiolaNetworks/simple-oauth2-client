<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Enums;

use InvalidArgumentException;

/**
 * Claim of the resource owner that is handed to the group handler.
 *
 * GROUPS:       the directory groups of the user, sent by the "profile" scope.
 * ENTITLEMENTS: the application entitlements of the user (authentik 2024.12+),
 *               sent by the "entitlements" scope.
 *
 * The two are never merged: in ENTITLEMENTS mode the directory groups are
 * ignored, so a directory group named like an entitlement grants nothing.
 */
enum GroupSourceEnum: string
{
    case GROUPS       = 'groups';
    case ENTITLEMENTS = 'entitlements';

    /**
     * Source set in "oauth.group_source".
     *
     * A missing value means GROUPS, so configs published before the option
     * existed keep their behaviour. An unknown value throws instead of falling
     * back to GROUPS: a typo must not silently switch the authorization source.
     */
    public static function fromConfig(): self
    {
        $value = config('oauth.group_source');

        if ($value === null || $value === '') {
            return self::GROUPS;
        }

        if ($value instanceof self) {
            return $value;
        }

        $source = is_string($value) ? self::tryFrom(strtolower(trim($value))) : null;

        if ($source === null) {
            throw new InvalidArgumentException(sprintf(
                'Invalid "oauth.group_source" value. Allowed values: %s.',
                implode(', ', array_column(self::cases(), 'value')),
            ));
        }

        return $source;
    }

    /**
     * Extra scope to request so the provider sends this claim, if any.
     */
    public function scope(): ?string
    {
        return match ($this) {
            self::GROUPS       => null,
            self::ENTITLEMENTS => 'entitlements',
        };
    }

    /**
     * Names in this source's claim of the resource owner.
     *
     * A missing or malformed claim yields an empty list, never the other source.
     *
     * @param  array<array-key, mixed> $claims
     * @return list<string>
     */
    public function extract(array $claims): array
    {
        $values = $claims[$this->value] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_string(...)));
    }
}
