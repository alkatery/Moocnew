<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks a learner's progress through a single lesson (PRD §5.ج).
 *
 * @property int $enrollment_id
 * @property int $lesson_id
 * @property Carbon|null $completed_at
 * @property int $video_position
 */
final class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'completed_at',
        'video_position',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'video_position' => 'integer',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
