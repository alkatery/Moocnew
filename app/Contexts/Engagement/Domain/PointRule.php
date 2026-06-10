<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Domain;

/**
 * How many points each rewarded action is worth (gamification).
 */
enum PointRule: string
{
    case LessonCompleted = 'lesson_completed';
    case CourseCompleted = 'course_completed';
    case PathCompleted = 'path_completed';
    case ReviewWritten = 'review_written';

    public function points(): int
    {
        return match ($this) {
            self::LessonCompleted => 10,
            self::CourseCompleted => 100,
            self::PathCompleted => 250,
            self::ReviewWritten => 15,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LessonCompleted => 'إكمال درس',
            self::CourseCompleted => 'إكمال دورة',
            self::PathCompleted => 'إكمال مسار',
            self::ReviewWritten => 'كتابة مراجعة',
        };
    }
}
