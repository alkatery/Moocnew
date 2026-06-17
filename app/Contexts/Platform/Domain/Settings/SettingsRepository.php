<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Domain\Settings;

/**
 * Contract for reading and writing platform settings. The Domain layer
 * depends only on this interface; concrete persistence (Eloquent) and
 * caching (Redis) live in Infrastructure and are wired via the container.
 */
interface SettingsRepository
{
    /**
     * Return the stored value for the key, cast to its native type, or
     * null when the key has never been set. Callers apply their own
     * defaults (typically from config) when null is returned.
     */
    public function get(SettingKey $key): mixed;

    /**
     * Persist a value for the key. The value is cast to the key's declared
     * type before storage.
     */
    public function set(SettingKey $key, mixed $value): void;

    /**
     * Remove a stored value, reverting the key to "never set".
     */
    public function forget(SettingKey $key): void;
}
