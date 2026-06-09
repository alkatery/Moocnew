<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Infrastructure\Persistence;

use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;

/**
 * Persists settings in PostgreSQL via Eloquent. Values are stored as JSON
 * text so any supported scalar/array round-trips losslessly, and are cast
 * back to their declared native type on read.
 */
final class DatabaseSettingsRepository implements SettingsRepository
{
    public function get(SettingKey $key): mixed
    {
        $row = SettingModel::query()->where('key', $key->value)->first();

        if ($row === null) {
            return null;
        }

        $decoded = $row->value === null
            ? null
            : json_decode($row->value, true, 512, JSON_THROW_ON_ERROR);

        return $key->valueType()->cast($decoded);
    }

    public function set(SettingKey $key, mixed $value): void
    {
        $cast = $key->valueType()->cast($value);

        SettingModel::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'value' => json_encode($cast, JSON_THROW_ON_ERROR),
                'type' => $key->valueType()->value,
            ],
        );
    }

    public function forget(SettingKey $key): void
    {
        SettingModel::query()->where('key', $key->value)->delete();
    }
}
