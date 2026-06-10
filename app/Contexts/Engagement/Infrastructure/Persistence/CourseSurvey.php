<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner's NELC satisfaction survey for a completed course: four 1–5
 * axes (overall, content, instructor, platform) and an optional comment.
 * One response per (course, user); resubmitting updates the response.
 *
 * @property int $course_id
 * @property int $user_id
 * @property int $overall
 * @property int $content_quality
 * @property int $instructor_quality
 * @property int $platform_quality
 * @property string|null $comment
 */
final class CourseSurvey extends Model
{
    protected $fillable = [
        'course_id',
        'user_id',
        'overall',
        'content_quality',
        'instructor_quality',
        'platform_quality',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'overall' => 'integer',
            'content_quality' => 'integer',
            'instructor_quality' => 'integer',
            'platform_quality' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
