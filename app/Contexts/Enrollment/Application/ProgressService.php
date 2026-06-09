<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Enrollment\Infrastructure\Persistence\LessonProgress;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Records lesson progress and the video resume position, and keeps the
 * enrollment's overall completion percentage in sync (PRD §5.ج). When every
 * lesson is complete the enrollment transitions to `completed`.
 */
final class ProgressService
{
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

            if ($completed && $progress->completed_at === null) {
                $progress->completed_at = Date::now();
            }

            $progress->save();

            $this->recalculate($enrollment);

            return $progress;
        });
    }

    /**
     * Recompute the completion percentage and complete the enrollment once
     * all lessons are done.
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

        $percent = $totalLessons > 0
            ? (int) floor(($completedLessons / $totalLessons) * 100)
            : 0;

        $enrollment->progress_percent = $percent;

        $justCompleted = false;

        if (
            $percent === 100
            && $enrollment->status === EnrollmentStatus::Active
            && $enrollment->status->canTransitionTo(EnrollmentStatus::Completed)
        ) {
            $enrollment->status = EnrollmentStatus::Completed;
            $enrollment->completed_at = Date::now();
            $justCompleted = true;
        }

        $enrollment->save();

        if ($justCompleted) {
            Event::dispatch(new EnrollmentCompleted(
                $enrollment->getKey(),
                $enrollment->user_id,
                $enrollment->course_id,
            ));
        }
    }
}
