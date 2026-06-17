<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\SectionGate;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Enrollment\Infrastructure\Persistence\LessonProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The learner's per-lesson progress for a course: which lessons are done and
 * the saved video resume position for each — so the player can resume where
 * they left off and tick completed lessons (PRD §5.ج, advanced tracking).
 */
final class CourseProgressController extends Controller
{
    public function show(Request $request, Course $course): JsonResponse
    {
        $enrollment = Enrollment::query()
            ->where('user_id', $request->user()->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        if ($enrollment === null) {
            return response()->json([
                'data' => ['enrolled' => false, 'percent' => 0, 'lessons' => []],
            ]);
        }

        $lessons = LessonProgress::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->get(['lesson_id', 'video_position', 'completed_at'])
            ->map(fn (LessonProgress $p): array => [
                'lesson_id' => $p->lesson_id,
                'video_position' => $p->video_position,
                'completed' => $p->completed_at !== null,
            ]);

        return response()->json([
            'data' => [
                'enrolled' => true,
                'status' => $enrollment->status->value,
                'percent' => $enrollment->progress_percent,
                'completed' => $enrollment->status === EnrollmentStatus::Completed,
                'lessons' => $lessons,
                // #3: وحدات مقفلة ببوّابة وحدة سابقة لم تُجتَز (للعرض في الواجهة).
                'locked_sections' => app(SectionGate::class)->lockedSectionIds($request->user(), $course),
            ],
        ]);
    }
}
