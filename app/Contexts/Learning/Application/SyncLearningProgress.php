<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Application;

use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Models\User;

/**
 * When a learner completes a course, propagate that into learning paths
 * (sequence + path certificate) and personal study plans (auto-close).
 * Listening to the event keeps Learning decoupled from Enrollment.
 */
final class SyncLearningProgress
{
    public function __construct(
        private readonly PathProgressService $paths,
        private readonly StudyPlanService $plans,
    ) {}

    public function handle(EnrollmentCompleted $event): void
    {
        $user = User::query()->find($event->userId);

        if ($user === null) {
            return;
        }

        $this->paths->syncCompletionFor($user, $event->courseId);
        $this->plans->syncCompletionFor($user, $event->courseId);
    }
}
