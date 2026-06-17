<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Application\QuizAttemptService;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Enrollment\Application\SectionGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\SubmitAttemptRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\QuizAttemptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly QuizAttemptService $attempts,
        private readonly CourseAccess $access,
    ) {}

    /**
     * Start (or resume) an attempt and return the questions to answer —
     * shuffled if configured, and always without the answer key.
     */
    public function start(Request $request, Quiz $quiz, SectionGate $gate): JsonResponse
    {
        $user = $request->user();
        $course = $quiz->course;
        abort_unless($this->access->canParticipate($user, $course), 403);

        // #3: بوّابة الوحدة — لا يبدأ غير الطاقم اختبار وحدة مقفلة.
        abort_unless(
            $this->access->isStaffFor($user, $course) || $gate->isSectionUnlockedFor($user, $course, $quiz->section_id),
            403,
        );

        $attempt = $this->attempts->start($quiz, $user);

        $questions = $quiz->questions()->get();
        // Random-draw quizzes: only the attempt's frozen subset is served.
        if ($attempt->question_ids !== null) {
            $questions = $questions->whereIn('id', $attempt->question_ids)->values();
        }
        if ($quiz->shuffle) {
            $questions = $questions->shuffle()->values();
        }

        return response()->json([
            'attempt' => new QuizAttemptResource($attempt),
            'questions' => QuestionResource::collection($questions),
        ], $attempt->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, QuizAttempt $attempt): QuizAttemptResource
    {
        $isOwner = $attempt->user_id === $request->user()->getKey();
        abort_unless($isOwner || $this->access->isStaffFor($request->user(), $attempt->quiz->course), 403);

        return new QuizAttemptResource($attempt);
    }

    public function submit(SubmitAttemptRequest $request, QuizAttempt $attempt): QuizAttemptResource
    {
        abort_unless($attempt->user_id === $request->user()->getKey(), 403);

        /** @var array<int, mixed> $answers */
        $answers = (array) $request->validated('answers', []);

        $finalised = $this->attempts->submit($attempt, $answers);

        // Instant formative feedback: per-question correctness, the answer
        // key and the instructor's explanation — only AFTER submission.
        $answersByQuestion = $finalised->answers()->get()->keyBy('question_id');
        $questions = $finalised->quiz->questions()->get();
        if ($finalised->question_ids !== null) {
            $questions = $questions->whereIn('id', $finalised->question_ids)->values();
        }

        $feedback = $questions->map(fn ($q) => [
            'question_id' => $q->id,
            'body' => $q->body,
            'is_correct' => (bool) ($answersByQuestion[$q->id]->is_correct ?? false),
            'correct' => $q->correct,
            'explanation' => $q->explanation,
            'points' => $q->points,
        ])->values();

        return (new QuizAttemptResource($finalised))
            ->additional(['feedback' => $feedback]);
    }
}
