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
 * the WEIGHTED average of all components — every quiz/assignment carries an
 * instructor-set `weight` (default 1), which is how per-section grading
 * emphasis is expressed. A course with no quizzes or assignments has no
 * grade (null), so completion stays gated on lessons.
 */
final class CourseGradeService implements CourseGradeProvider
{
    public function gradeFor(int $userId, int $courseId): ?int
    {
        /** @var list<array{score: int, weight: int}> $components */
        $components = [];

        // Quizzes: best submitted attempt score per quiz (0 if never passed).
        $quizzes = Quiz::query()
            ->where('course_id', $courseId)
            ->get(['id', 'weight']);

        foreach ($quizzes as $quiz) {
            $best = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $userId)
                ->whereNotNull('submitted_at')
                ->max('score');

            $components[] = ['score' => (int) ($best ?? 0), 'weight' => max(1, (int) $quiz->weight)];
        }

        // Assignments: graded submission percentage (0 if unsubmitted/ungraded).
        $assignments = Assignment::query()
            ->where('course_id', $courseId)
            ->get(['id', 'points', 'weight']);

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

            $components[] = ['score' => max(0, min(100, $percent)), 'weight' => max(1, (int) $assignment->weight)];
        }

        if ($components === []) {
            return null;
        }

        return self::weightedOverall($components);
    }

    /**
     * حساب المتوسط الموزون لمكوّنات التقييم (مشترك مع GradebookService — DRY).
     *
     * كل مكوّن: ['score' => int 0–100, 'weight' => int >= 1].
     * تُعيد null إن كانت المصفوفة فارغة.
     *
     * @param  list<array{score: int, weight: int}>  $components
     */
    public static function weightedOverall(array $components): ?int
    {
        if ($components === []) {
            return null;
        }

        $totalWeight = array_sum(array_column($components, 'weight'));
        $weightedSum = array_sum(array_map(
            static fn (array $c): int => $c['score'] * $c['weight'],
            $components,
        ));

        return (int) round($weightedSum / max(1, $totalWeight));
    }
}
