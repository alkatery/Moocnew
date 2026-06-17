<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner's private note on a lesson, optionally anchored to a video
 * timestamp so clicking it can seek the player.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int|null $at_seconds
 * @property string $body
 */
final class LessonNote extends Model
{
    protected $fillable = [
        'user_id',
        'lesson_id',
        'at_seconds',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'at_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
