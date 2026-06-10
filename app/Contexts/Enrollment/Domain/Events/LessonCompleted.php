<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Events;

/**
 * Raised the first time a learner marks a lesson complete. Engagement
 * listens to award points and advance the learning-day streak, keeping the
 * two contexts decoupled.
 */
final readonly class LessonCompleted
{
    public function __construct(
        public int $userId,
        public int $lessonId,
        public int $courseId,
    ) {}
}
