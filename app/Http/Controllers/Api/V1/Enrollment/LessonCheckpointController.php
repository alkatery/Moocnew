<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Assessment\Domain\AnswerGrader;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Application\LessonAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-video formative checkpoints: questions anchored to timestamps that
 * pause the player and check understanding instantly — never graded, the
 * Coursera-style "in-video quiz" pattern.
 */
final class LessonCheckpointController extends Controller
{
    /**
     * The lesson's checkpoints with learner-safe question payloads
     * (no answer key).
     */
    public function index(Request $request, Lesson $lesson, LessonAccess $access): JsonResponse
    {
        abort_unless($access->canAccess($request->user(), $lesson), 403);

        $checkpoints = collect($lesson->checkpoints ?? []);
        $questions = Question::query()
            ->whereIn('id', $checkpoints->pluck('question_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $payload = $checkpoints
            ->map(function (array $cp) use ($questions) {
                $question = $questions->get((int) ($cp['question_id'] ?? 0));

                return $question === null ? null : [
                    'at_seconds' => (int) ($cp['at_seconds'] ?? 0),
                    'question' => new QuestionResource($question),
                ];
            })
            ->filter()
            ->sortBy('at_seconds')
            ->values();

        return response()->json(['data' => $payload]);
    }

    /**
     * Check a learner's checkpoint answer and return instant feedback with
     * the instructor's explanation. Formative only — nothing is recorded
     * against the grade.
     */
    public function answer(
        Request $request,
        Lesson $lesson,
        Question $question,
        LessonAccess $access,
        AnswerGrader $grader,
    ): JsonResponse {
        abort_unless($access->canAccess($request->user(), $lesson), 403);

        $isCheckpoint = collect($lesson->checkpoints ?? [])
            ->contains(fn (array $cp) => (int) ($cp['question_id'] ?? 0) === $question->getKey());
        abort_unless($isCheckpoint, 404);

        $validated = $request->validate(['answer' => ['required']]);

        $correct = $grader->isCorrect($question->type, $question->correct, $validated['answer']);

        return response()->json([
            'correct' => $correct,
            'explanation' => $question->explanation,
        ]);
    }
}
