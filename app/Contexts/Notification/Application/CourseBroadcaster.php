<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Notification\Infrastructure\Notifications\CourseAnnouncementNotification;
use App\Contexts\Notification\Infrastructure\Notifications\CourseBulkEmailNotification;
use App\Contexts\Notification\Infrastructure\Persistence\CourseAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * خدمة البثّ الجماعي للمقررات (C3 — PRD §5.ط).
 *
 * تنسّق إنشاء الإعلانات وإرسال البريد الجماعي للمتعلّمين النشطين.
 * تكرّر على المستلمين بـ chunk لتفادي تحميل آلاف المستخدمين دفعةً.
 * كل إرسال فردي ($user->notify) → مهمة مُطابورة منفصلة (لا BCC).
 */
final class CourseBroadcaster
{
    /**
     * أنشئ إعلاناً وابثّه لكل الملتحقين النشطين في المقرر.
     *
     * @return int عدد المستلمين الذين دُفعت لهم مهام إشعار
     */
    public function announce(Course $course, User $author, string $title, string $body): int
    {
        // إنشاء صفّ الإعلان — مصدر الحقيقة للعرض الدائم
        $announcement = CourseAnnouncement::query()->create([
            'course_id' => $course->getKey(),
            'author_id' => $author->getKey(),
            'title' => $title,
            'body' => $body,
        ]);

        $count = 0;

        // تكرار chunk لتفادي تحميل آلاف المستخدمين في الذاكرة دفعةً
        // نستخدم chunk() العادي لأن chunkById يتطلّب عموداً مُحدَّد النطاق
        $this->activeEnrollmentsQuery($course->getKey())
            ->chunk(500, function ($enrollments) use ($course, $announcement, $title, $body, &$count): void {
                foreach ($enrollments as $enrollment) {
                    /** @var User $user */
                    $user = $enrollment->user;

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new CourseAnnouncementNotification(
                        courseTitle: $course->title,
                        announcementId: $announcement->id,
                        title: $title,
                        body: $body,
                    ));

                    $count++;
                }
            });

        return $count;
    }

    /**
     * ابعث بريداً جماعياً لكل الملتحقين النشطين في المقرر.
     * النصّ لا يُخزَّن — تقليل بيانات (PDPL).
     *
     * @param  string  $audience  القيمة المدعومة حالياً: all_active (الافتراضي)
     * @return int عدد المستلمين الذين دُفعت لهم مهام بريد
     */
    public function bulkEmail(
        Course $course,
        string $subject,
        string $body,
        string $audience = 'all_active',
    ): int {
        $count = 0;

        // v1: all_active فقط (audience محجوز للتوسعة — لا هندسة زائدة)
        $this->activeEnrollmentsQuery($course->getKey())
            ->chunk(500, function ($enrollments) use ($course, $subject, $body, &$count): void {
                foreach ($enrollments as $enrollment) {
                    /** @var User $user */
                    $user = $enrollment->user;

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new CourseBulkEmailNotification(
                        courseTitle: $course->title,
                        subject: $subject,
                        body: $body,
                    ));

                    $count++;
                }
            });

        return $count;
    }

    /**
     * استعلام الالتحاقات النشطة مع تحميل المستخدم.
     * شرط الوصول: Active أو Completed + نافذة غير منتهية.
     *
     * @return Builder<Enrollment>
     */
    private function activeEnrollmentsQuery(int $courseId): Builder
    {
        return Enrollment::query()
            ->with('user')
            ->where('course_id', $courseId)
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Completed->value,
            ])
            ->where(fn ($q) => $q
                ->whereNull('access_expires_at')
                ->orWhere('access_expires_at', '>', now())
            );
    }
}
