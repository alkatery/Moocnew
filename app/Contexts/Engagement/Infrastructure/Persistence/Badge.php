<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A badge a learner has earned (one row per user + badge key).
 *
 * @property int $user_id
 * @property string $badge
 * @property Carbon $earned_at
 */
final class Badge extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'badge',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
        ];
    }
}
