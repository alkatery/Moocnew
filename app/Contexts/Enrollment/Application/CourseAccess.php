<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Permission;
use App\Models\User;

/**
 * Course-level access checks reused across contexts (assessments, etc.):
 * a learner with an active enrollment, the owning instructor, or a
 * reviewer/admin may access course-bound activities (PRD §5.ج).
 */
final class CourseAccess
{
    public function hasActiveEnrollment(User $user, int $courseId): bool
    {
        return Enrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $courseId)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->where(fn ($q) => $q->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()))
            ->exists();
    }

    /**
     * Whether the learner finished the course — required before answering
     * the NELC satisfaction survey.
     */
    public function hasCompletedEnrollment(User $user, int $courseId): bool
    {
        return Enrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $courseId)
            ->where('status', EnrollmentStatus::Completed->value)
            ->exists();
    }

    public function isStaffFor(User $user, Course $course): bool
    {
        return $course->instructor_id === $user->getKey()
            || $course->hasCoAuthor($user)              // E3: المؤلّف المشارك = طاقم المقرر.
            || $user->can(Permission::ReviewCourses->value);
    }

    public function canParticipate(User $user, Course $course): bool
    {
        return $this->isStaffFor($user, $course) || $this->hasActiveEnrollment($user, $course->getKey());
    }
}
