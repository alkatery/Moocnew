<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * A stored notification preference for a (user, type, channel) (PRD §5.ط).
 *
 * @property int $user_id
 * @property string $type
 * @property string $channel
 * @property bool $enabled
 */
final class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
