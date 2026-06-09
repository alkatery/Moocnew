<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Notification\Infrastructure\Notifications\CourseCompletedNotification;
use App\Models\User;

/**
 * Congratulates the learner on completing a course across enabled channels.
 */
final class SendCourseCompletion
{
    public function handle(EnrollmentCompleted $event): void
    {
        $user = User::query()->find($event->userId);
        $course = Course::query()->find($event->courseId);

        if ($user === null || $course === null) {
            return;
        }

        $user->notify(new CourseCompletedNotification($course->title));
    }
}
