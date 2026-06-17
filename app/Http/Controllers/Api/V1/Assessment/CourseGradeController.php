<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Enrollment\Domain\Grading\CourseGradeProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The learner's gradebook for a course (the Edraak "درجاتي" view): each
 * assessment's score plus the overall grade and whether it meets the
 * course's passing grade.
 */
final class CourseGradeController extends Controller
{
    public function __invoke(
        Request $request,
        Course $course,
        CourseAccess $access,
        CourseGradeProvider $grades,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($access->canParticipate($user, $course), 403);

        $quizzes = Quiz::query()->where('course_id', $course->id)->get(['id', 'title', 'pass_mark']);
        $quizRows = $quizzes->map(function (Quiz $quiz) use ($user): array {
            $best = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $user->getKey())
                ->whereNotNull('submitted_at')
                ->max('score');

            return [
                'type' => 'quiz',
                'id' => $quiz->id,
                'title' => $quiz->title,
                'score' => $best === null ? null : (int) $best,
                'pass_mark' => $quiz->pass_mark,
                'passed' => $best !== null && (int) $best >= $quiz->pass_mark,
            ];
        });

        $assignments = Assignment::query()->where('course_id', $course->id)->get(['id', 'title', 'points']);
        $assignmentRows = $assignments->map(function (Assignment $assignment) use ($user): array {
            $submission = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $user->getKey())
                ->first();

            $points = max(1, (int) $assignment->points);
            $score = ($submission !== null && $submission->graded_at !== null)
                ? (int) round(((int) $submission->grade / $points) * 100)
                : null;

            return [
                'type' => 'assignment',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'score' => $score,
                'pass_mark' => null,
                'passed' => $score !== null,
            ];
        });

        $overall = $grades->gradeFor($user->getKey(), $course->id);
        $bar = (int) ($course->passing_grade ?? 0);

        return response()->json([
            'data' => [
                'passing_grade' => $bar,
                'overall' => $overall,
                'passed' => $bar <= 0 || $overall === null || $overall >= $bar,
                'components' => $quizRows->concat($assignmentRows)->values(),
            ],
        ]);
    }
}
