<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Domain;

/**
 * Pure, framework-free grading of a single objective answer (PRD §5.هـ).
 * Kept in the Domain layer so the scoring rules are unit-testable in
 * isolation and shared by both live grading and any re-grading.
 */
final class AnswerGrader
{
    /**
     * @param  mixed  $correct  the question's answer key
     * @param  mixed  $answer  the learner's submitted answer
     */
    public function isCorrect(QuestionType $type, mixed $correct, mixed $answer): bool
    {
        return match ($type) {
            QuestionType::Mcq => $this->gradeMcq($correct, $answer),
            QuestionType::TrueFalse => $this->gradeTrueFalse($correct, $answer),
            QuestionType::ShortAnswer => $this->gradeShortAnswer($correct, $answer),
        };
    }

    /**
     * MCQ: the submitted set of choice ids must equal the correct set
     * (order-independent), supporting both single- and multi-select.
     */
    private function gradeMcq(mixed $correct, mixed $answer): bool
    {
        if (! is_array($correct) || ! is_array($answer)) {
            return false;
        }

        $expected = array_values(array_unique(array_map('strval', $correct)));
        $given = array_values(array_unique(array_map('strval', $answer)));

        sort($expected);
        sort($given);

        return $expected === $given;
    }

    private function gradeTrueFalse(mixed $correct, mixed $answer): bool
    {
        return is_bool($answer) ? ((bool) $correct === $answer) : false;
    }

    /**
     * Short answer: case-insensitive, whitespace- and Arabic-diacritic
     * normalised match against any accepted answer.
     */
    private function gradeShortAnswer(mixed $correct, mixed $answer): bool
    {
        if (! is_string($answer)) {
            return false;
        }

        $accepted = is_array($correct) ? $correct : [$correct];
        $normalisedAnswer = $this->normalise($answer);

        foreach ($accepted as $candidate) {
            if (is_string($candidate) && $this->normalise($candidate) === $normalisedAnswer) {
                return true;
            }
        }

        return false;
    }

    private function normalise(string $value): string
    {
        // Strip Arabic diacritics (tashkeel) and tatweel, collapse spaces.
        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value));
    }
}
