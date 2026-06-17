<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\Events\EnrollmentActivated;
use App\Contexts\Notification\Infrastructure\Notifications\EnrollmentConfirmedNotification;
use App\Models\User;

/**
 * Confirms an enrollment to the learner across their enabled channels.
 */
final class SendEnrollmentConfirmation
{
    public function handle(EnrollmentActivated $event): void
    {
        $user = User::query()->find($event->userId);
        $course = Course::query()->find($event->courseId);

        if ($user === null || $course === null) {
            return;
        }

        $user->notify(new EnrollmentConfirmedNotification($course->title));
    }
}
