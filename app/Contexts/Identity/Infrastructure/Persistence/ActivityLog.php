<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail record (PRD §5.ي, §6). Has a creation timestamp
 * only — entries are never updated.
 *
 * @property int|null $causer_id
 * @property string $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array|null $properties
 */
final class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'causer_id',
        'event',
        'subject_type',
        'subject_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
