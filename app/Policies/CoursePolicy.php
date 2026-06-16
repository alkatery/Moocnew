<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Permission;
use App\Models\User;

/**
 * Authorization for courses (PRD §4). The Super Admin bypasses every check
 * via the Gate::before hook in IdentityServiceProvider, so these rules
 * describe instructors, supervisors, students and visitors.
 */
final class CoursePolicy
{
    /**
     * Published courses are visible to everyone (including guests); drafts
     * and in-review courses only to their owner, a reviewer, or a co-author.
     */
    public function view(?User $user, Course $course): bool
    {
        if ($course->status === CourseStatus::Published) {
            return true;
        }

        return $user !== null && (
            $this->owns($user, $course)
            || $user->can(Permission::ReviewCourses->value)
            || $course->hasCoAuthor($user)   // E3: المؤلّف المشارك يفتح مسوّدته في الاستوديو.
        );
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCourses->value);
    }

    public function update(User $user, Course $course): bool
    {
        if ($user->can(Permission::ReviewCourses->value)) {
            return true;
        }

        // E3: المؤلّف المشارك يحرّر هذا المقرر كالمالك (محتوى فقط — لا حذف/لا إدارة أعضاء).
        if ($course->hasCoAuthor($user)) {
            return true;
        }

        return $user->can(Permission::ManageCourses->value) && $this->owns($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can(Permission::ManageCourses->value) && $this->owns($user, $course);
    }

    /**
     * Submitting a draft for review is the owner's action.
     */
    public function submit(User $user, Course $course): bool
    {
        return $user->can(Permission::ManageCourses->value) && $this->owns($user, $course);
    }

    /**
     * Approving or rejecting a course is a reviewer's action.
     */
    public function review(User $user, Course $course): bool
    {
        return $user->can(Permission::ReviewCourses->value);
    }

    /**
     * إدارة فريق التأليف (إضافة/إزالة المؤلّفين المشاركين).
     * المالك فقط (+ super_admin عبر Gate::before).
     * لا co-author، لا مراجع — منع تصعيد الصلاحيات.
     */
    public function manageMembers(User $user, Course $course): bool
    {
        return $user->can(Permission::ManageCourses->value) && $this->owns($user, $course);
    }

    private function owns(User $user, Course $course): bool
    {
        return $course->instructor_id === $user->getKey();
    }
}
