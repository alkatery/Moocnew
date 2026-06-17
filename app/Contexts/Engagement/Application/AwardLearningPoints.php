<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Application;

use App\Contexts\Engagement\Domain\PointRule;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Enrollment\Domain\Events\LessonCompleted;

/**
 * Translates learning events into gamification awards, keeping Engagement
 * decoupled from Enrollment. Idempotent via per-source keys.
 */
final class AwardLearningPoints
{
    public function __construct(
        private readonly GamificationService $gamification,
    ) {}

    public function onLessonCompleted(LessonCompleted $event): void
    {
        $this->gamification->award(
            $event->userId,
            PointRule::LessonCompleted,
            "lesson:{$event->lessonId}",
        );
    }

    public function onCourseCompleted(EnrollmentCompleted $event): void
    {
        $this->gamification->award(
            $event->userId,
            PointRule::CourseCompleted,
            "course:{$event->courseId}",
        );
    }
}
