<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Application;

use App\Contexts\Assessment\Domain\AnswerGrader;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Enrollment\Application\CourseCompletionService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates quiz attempts (PRD §5.هـ): starting an attempt within the
 * allowed limit, and submitting answers for objective auto-grading. A late
 * submission (past the time limit) is finalised with a zero score.
 */
final class QuizAttemptService
{
    public function __construct(
        private readonly AnswerGrader $grader,
        private readonly CourseCompletionService $completion,
    ) {}

    public function start(Quiz $quiz, User $user): QuizAttempt
    {
        return DB::transaction(function () use ($quiz, $user): QuizAttempt {
            $resumable = QuizAttempt::query()
                ->where('quiz_id', $quiz->getKey())
                ->where('user_id', $user->getKey())
                ->whereNull('submitted_at')
                ->lockForUpdate()
                ->first();

            if ($resumable !== null) {
                return $resumable;
            }

            if ($quiz->max_attempts !== null) {
                $used = QuizAttempt::query()
                    ->where('quiz_id', $quiz->getKey())
                    ->where('user_id', $user->getKey())
                    ->whereNotNull('submitted_at')
                    ->count();

                if ($used >= $quiz->max_attempts) {
                    throw ValidationException::withMessages([
                        'attempt' => ['تم استنفاد عدد المحاولات المسموح بها.'],
                    ]);
                }
            }

            return QuizAttempt::query()->create([
                'quiz_id' => $quiz->getKey(),
                'user_id' => $user->getKey(),
                // Random-draw quizzes freeze the learner's question subset on
                // the attempt, so resume and grading see the same questions.
                'question_ids' => $this->drawQuestionIds($quiz),
                'started_at' => Date::now(),
            ]);
        });
    }

    /**
     * When the quiz draws N random questions, pick them now; otherwise null
     * (= the quiz's full ordered selection).
     *
     * @return list<int>|null
     */
    private function drawQuestionIds(Quiz $quiz): ?array
    {
        $all = $quiz->questions()->pluck('question_bank.id')->all();

        if ($quiz->draw_count === null || $quiz->draw_count >= count($all)) {
            return null;
        }

        shuffle($all);

        return array_values(array_slice($all, 0, max(1, $quiz->draw_count)));
    }

    /**
     * Grade and finalise an attempt.
     *
     * @param  array<int, mixed>  $answers  map of question_id => submitted answer
     */
    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        if ($attempt->isSubmitted()) {
            throw ValidationException::withMessages([
                'attempt' => ['تم تسليم هذه المحاولة مسبقاً.'],
            ]);
        }

        $finalised = DB::transaction(function () use ($attempt, $answers): QuizAttempt {
            $quiz = $attempt->quiz()->with('questions')->firstOrFail();
            $expired = $this->isExpired($attempt, $quiz);

            // A random-draw attempt is graded against its frozen subset only.
            $questions = $attempt->question_ids === null
                ? $quiz->questions
                : $quiz->questions->whereIn('id', $attempt->question_ids)->values();

            $totalPoints = 0;
            $earnedPoints = 0;

            foreach ($questions as $question) {
                $submitted = $answers[$question->id] ?? null;
                $correct = ! $expired && $this->grader->isCorrect($question->type, $question->correct, $submitted);

                $totalPoints += $question->points;
                if ($correct) {
                    $earnedPoints += $question->points;
                }

                $attempt->answers()->updateOrCreate(
                    ['question_id' => $question->id],
                    ['answer' => $submitted, 'is_correct' => $correct],
                );
            }

            $score = ($totalPoints > 0 && ! $expired)
                ? (int) floor(($earnedPoints / $totalPoints) * 100)
                : 0;

            $attempt->fill([
                'score' => $score,
                'passed' => ! $expired && $score >= $quiz->pass_mark,
                'submitted_at' => Date::now(),
            ])->save();

            return $attempt->refresh();
        });

        // Passing the final assessment can complete a course whose lessons
        // are already done — re-evaluate the learner's enrollment.
        $this->completion->evaluateFor($finalised->user_id, $finalised->quiz->course_id);

        return $finalised;
    }

    private function isExpired(QuizAttempt $attempt, Quiz $quiz): bool
    {
        if ($quiz->time_limit_minutes === null) {
            return false;
        }

        return Date::now()->greaterThan(
            $attempt->started_at->copy()->addMinutes($quiz->time_limit_minutes),
        );
    }
}
