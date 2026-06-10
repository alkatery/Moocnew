<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Enrollment\Domain\Grading\CourseGradeProvider;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;

/**
 * Decides when an active enrollment becomes completed (the Edraak model):
 * every lesson done AND, when the course defines a passing grade, the
 * learner's assessment grade meets it. Both the lesson-progress flow and the
 * assessment-grading flow funnel through here, so completing the final exam
 * after the last lesson — or vice-versa — triggers the certificate exactly
 * once.
 */
final class CourseCompletionService
{
    public function __construct(
        private readonly CourseGradeProvider $grades,
    ) {}

    /**
     * Re-evaluate an enrollment and complete it if its requirements are met.
     * Idempotent: a non-active enrollment, or one not yet eligible, is left
     * untouched.
     */
    public function evaluate(Enrollment $enrollment): void
    {
        if ($enrollment->status !== EnrollmentStatus::Active) {
            return;
        }

        if ($enrollment->progress_percent < 100) {
            return;
        }

        if (! $enrollment->status->canTransitionTo(EnrollmentStatus::Completed)) {
            return;
        }

        $course = Course::query()->find($enrollment->course_id);
        if ($course === null) {
            return;
        }

        $grade = $this->grades->gradeFor($enrollment->user_id, $enrollment->course_id);

        if (! $this->meetsPassingGrade($course, $grade)) {
            return;
        }

        $enrollment->status = EnrollmentStatus::Completed;
        $enrollment->completed_at = Date::now();
        $enrollment->save();

        Event::dispatch(new EnrollmentCompleted(
            $enrollment->getKey(),
            $enrollment->user_id,
            $enrollment->course_id,
            $grade,
        ));
    }

    /**
     * Re-evaluate the active enrollment of a given learner in a course, if
     * any — used by the assessment flow after a quiz/assignment is graded.
     */
    public function evaluateFor(int $userId, int $courseId): void
    {
        $enrollment = Enrollment::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('status', EnrollmentStatus::Active->value)
            ->first();

        if ($enrollment !== null) {
            $this->evaluate($enrollment);
        }
    }

    /**
     * Whether the learner's grade satisfies the course's passing grade.
     * Ungraded courses (passing_grade 0) and courses with no assessments
     * (grade null) pass on lessons alone.
     */
    private function meetsPassingGrade(Course $course, ?int $grade): bool
    {
        $bar = (int) ($course->passing_grade ?? 0);

        if ($bar <= 0 || $grade === null) {
            return true;
        }

        return $grade >= $bar;
    }
}
