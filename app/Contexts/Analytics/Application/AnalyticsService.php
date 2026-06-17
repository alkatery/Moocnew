<?php

declare(strict_types=1);

namespace App\Contexts\Analytics\Application;

use App\Contexts\Analytics\Infrastructure\PresenceTracker;
use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Enrollment\Infrastructure\Persistence\LessonProgress;
use App\Contexts\Platform\Application\FeatureFlags;
use App\Models\User;

/**
 * Produces the aggregate dashboard figures (PRD §5.ي). Strictly aggregate:
 * counts and totals, never individual behavioural tracking, in line with
 * the PDPL stance taken in the PRD. Commerce metrics surface only when the
 * payments flag is on.
 */
final class AnalyticsService
{
    public function __construct(
        private readonly PresenceTracker $presence,
        private readonly FeatureFlags $features,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $overview = [
            'users_total' => User::query()->count(),
            'courses_published' => Course::query()->where('status', CourseStatus::Published->value)->count(),
            'enrollments_total' => Enrollment::query()->count(),
            'enrollments_active' => Enrollment::query()->where('status', EnrollmentStatus::Active->value)->count(),
            'enrollments_completed' => Enrollment::query()->where('status', EnrollmentStatus::Completed->value)->count(),
            'certificates_issued' => Certificate::query()->count(),
            'online_now' => $this->presence->onlineCount(),
            'commerce_enabled' => $this->features->paymentsEnabled(),
        ];

        return $overview;
    }

    /**
     * Lesson-by-lesson completion funnel for a course (PRD §5.ي): how many
     * enrolled learners completed each lesson, in order — revealing where
     * learners drop off. Aggregate counts only.
     *
     * @return array<string, mixed>
     */
    public function courseDropoff(int $courseId): array
    {
        $totalEnrollments = Enrollment::query()->where('course_id', $courseId)->count();

        $lessons = Lesson::query()
            ->join('sections', 'lessons.section_id', '=', 'sections.id')
            ->where('sections.course_id', $courseId)
            ->orderBy('sections.position')
            ->orderBy('lessons.position')
            ->get(['lessons.id', 'lessons.title']);

        $funnel = $lessons->map(function (Lesson $lesson) use ($courseId): array {
            $completed = LessonProgress::query()
                ->where('lesson_id', $lesson->id)
                ->whereNotNull('completed_at')
                ->whereHas('enrollment', fn ($q) => $q->where('course_id', $courseId))
                ->count();

            return [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'completed_count' => $completed,
            ];
        })->all();

        return [
            'course_id' => $courseId,
            'enrollments_total' => $totalEnrollments,
            'funnel' => $funnel,
        ];
    }
}
