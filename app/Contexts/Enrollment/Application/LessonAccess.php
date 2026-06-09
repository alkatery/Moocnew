<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Permission;
use App\Models\User;

/**
 * Decides whether a user may access a lesson's protected content (PRD §5.ج).
 * Access is granted for free-preview lessons, to the course's instructor and
 * to reviewers/admins, and to learners holding an active enrollment.
 */
final class LessonAccess
{
    public function canAccess(?User $user, Lesson $lesson): bool
    {
        if ($lesson->is_free_preview) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        $course = $lesson->section->course;

        if ($course->instructor_id === $user->getKey()) {
            return true;
        }

        if ($user->can(Permission::ReviewCourses->value)) {
            return true;
        }

        return Enrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->where(fn ($q) => $q->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()))
            ->exists();
    }

    /**
     * The learner's active enrollment in the lesson's course, if any.
     */
    public function activeEnrollmentFor(User $user, Lesson $lesson): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $lesson->section->course_id)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->first();
    }
}
