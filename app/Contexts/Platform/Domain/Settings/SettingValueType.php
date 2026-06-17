<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Domain\Settings;

/**
 * The supported value types for a stored setting. Values are persisted as
 * JSON text and re-hydrated into native PHP types according to this enum,
 * so that a caller reading `payments.enabled` always gets a real bool.
 */
enum SettingValueType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case String = 'string';
    case Json = 'json';

    /**
     * Cast a raw decoded value into the native type this enum represents.
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => (bool) $value,
            self::Integer => (int) $value,
            self::String => (string) $value,
            self::Json => $value,
        };
    }
}
