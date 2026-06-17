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
        // E2 — الخطوة 1: تحميل القسم والمقرر إن لم يُحمَّلا بعد.
        $lesson->loadMissing('section.course');
        $course = $lesson->section->course;

        // E2 — الخطوة 2: تحديد هوية الطاقم أولاً.
        // الطاقم (مالك المقرر / مراجع / أدمن) يتجاوز كل قيود الجدولة — يصل دائماً.
        $isStaff = $user !== null
            && ($course->instructor_id === $user->getKey()
                || $user->can(Permission::ReviewCourses->value));

        if ($isStaff) {
            return true;
        }

        // E2 — الخطوة 3: فحص ظهور القسم قبل short-circuit المعاينة المجانية.
        // الجدولة تتقدّم على is_free_preview — درس معاينة في قسم مجدول لا يتسرّب.
        if (! $lesson->section->isVisibleNow()) {
            return false;
        }

        // الخطوة 4 (القائم): المعاينة المجانية مفتوحة لمن حان قسمها.
        if ($lesson->is_free_preview) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        // الخطوة 5 (القائم): الالتحاق النشط يمنح الوصول.
        $enrolled = Enrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->where(fn ($q) => $q->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()))
            ->exists();

        if (! $enrolled) {
            return false;
        }

        // الخطوة 6 (#3): بوّابة الوحدة — تُقفَل الوحدة حتى يجتاز المتعلّم بوّابة
        // الوحدة السابقة. لا تُقيَّد دروس المعاينة المجانية (عادت true قبلاً).
        return app(SectionGate::class)->isUnlockedFor($user, $lesson->section);
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
