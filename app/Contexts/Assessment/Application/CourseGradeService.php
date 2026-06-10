<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Application;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Enrollment\Domain\Grading\CourseGradeProvider;

/**
 * Computes a learner's overall course grade (0–100) — the Edraak model in
 * which a certificate is earned by passing the course's assessments, not by
 * merely watching the videos.
 *
 * Each quiz contributes the learner's best submitted score; each assignment
 * contributes its graded percentage (grade ÷ points). The course grade is
 * the unweighted average of all components. A course with no quizzes or
 * assignments has no grade (null), so completion stays gated on lessons.
 */
final class CourseGradeService implements CourseGradeProvider
{
    public function gradeFor(int $userId, int $courseId): ?int
    {
        $components = [];

        // Quizzes: best submitted attempt score per quiz (0 if never passed).
        $quizIds = Quiz::query()->where('course_id', $courseId)->pluck('id');

        foreach ($quizIds as $quizId) {
            $best = QuizAttempt::query()
                ->where('quiz_id', $quizId)
                ->where('user_id', $userId)
                ->whereNotNull('submitted_at')
                ->max('score');

            $components[] = (int) ($best ?? 0);
        }

        // Assignments: graded submission percentage (0 if unsubmitted/ungraded).
        $assignments = Assignment::query()
            ->where('course_id', $courseId)
            ->get(['id', 'points']);

        foreach ($assignments as $assignment) {
            $submission = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $userId)
                ->whereNotNull('graded_at')
                ->first();

            $points = max(1, (int) $assignment->points);
            $percent = $submission !== null
                ? (int) round(((int) $submission->grade / $points) * 100)
                : 0;

            $components[] = max(0, min(100, $percent));
        }

        if ($components === []) {
            return null;
        }

        return (int) round(array_sum($components) / count($components));
    }
}
