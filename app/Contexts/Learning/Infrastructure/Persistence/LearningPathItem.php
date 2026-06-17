<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One course inside a learning path, at a given level/position.
 *
 * @property int $learning_path_id
 * @property int $course_id
 * @property int $level
 * @property int $position
 */
final class LearningPathItem extends Model
{
    protected $fillable = [
        'learning_path_id',
        'course_id',
        'level',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LearningPath, $this>
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
