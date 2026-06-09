<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent representation of a single row in the `settings` table.
 *
 * The raw value is stored as JSON text in the `value` column; type-aware
 * casting is the repository's responsibility, not the model's, so the
 * model stays a thin persistence record.
 *
 * @property string $key
 * @property string|null $value
 * @property string $type
 */
final class SettingModel extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'type',
    ];
}
