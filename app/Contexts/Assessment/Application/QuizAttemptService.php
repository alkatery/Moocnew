<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Application;

use App\Contexts\Assessment\Domain\AnswerGrader;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
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
                'started_at' => Date::now(),
            ]);
        });
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

        return DB::transaction(function () use ($attempt, $answers): QuizAttempt {
            $quiz = $attempt->quiz()->with('questions')->firstOrFail();
            $expired = $this->isExpired($attempt, $quiz);

            $totalPoints = 0;
            $earnedPoints = 0;

            foreach ($quiz->questions as $question) {
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
