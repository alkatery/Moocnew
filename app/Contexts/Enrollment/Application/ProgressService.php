<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Domain\Events\LessonCompleted;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Enrollment\Infrastructure\Persistence\LessonProgress;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Records lesson progress and the video resume position, and keeps the
 * enrollment's overall completion percentage in sync (PRD §5.ج). Completion
 * itself (including the passing-grade check) is delegated to the
 * {@see CourseCompletionService}.
 */
final class ProgressService
{
    public function __construct(
        private readonly CourseCompletionService $completion,
    ) {}

    public function record(
        Enrollment $enrollment,
        Lesson $lesson,
        ?int $videoPosition = null,
        bool $completed = false,
    ): LessonProgress {
        return DB::transaction(function () use ($enrollment, $lesson, $videoPosition, $completed): LessonProgress {
            /** @var LessonProgress $progress */
            $progress = LessonProgress::query()->firstOrNew([
                'enrollment_id' => $enrollment->getKey(),
                'lesson_id' => $lesson->getKey(),
            ]);

            if ($videoPosition !== null) {
                $progress->video_position = max(0, $videoPosition);
            }

            $justCompleted = false;
            if ($completed && $progress->completed_at === null) {
                $progress->completed_at = Date::now();
                $justCompleted = true;
            }

            $progress->save();

            $this->recalculate($enrollment);

            if ($justCompleted) {
                Event::dispatch(new LessonCompleted(
                    $enrollment->user_id,
                    $lesson->getKey(),
                    $enrollment->course_id,
                ));
            }

            return $progress;
        });
    }

    /**
     * Recompute the lesson-completion percentage, then let the completion
     * service decide whether the enrollment is now complete (it also applies
     * the course's passing-grade requirement).
     */
    private function recalculate(Enrollment $enrollment): void
    {
        $totalLessons = Lesson::query()
            ->whereHas('section', fn ($q) => $q->where('course_id', $enrollment->course_id))
            ->count();

        $completedLessons = LessonProgress::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->whereNotNull('completed_at')
            ->count();

        $enrollment->progress_percent = $totalLessons > 0
            ? (int) floor(($completedLessons / $totalLessons) * 100)
            : 0;

        $enrollment->save();

        $this->completion->evaluate($enrollment);
    }
}
