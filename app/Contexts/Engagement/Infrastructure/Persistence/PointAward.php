<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * An append-only record of a points award; `source_key` makes each award
 * idempotent (a lesson never grants points twice).
 *
 * @property int $user_id
 * @property string $reason
 * @property int $points
 * @property string|null $source_key
 */
final class PointAward extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'reason',
        'points',
        'source_key',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }
}
