<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Domain;

/**
 * Supported question types (PRD §5.هـ). All three are objectively
 * auto-gradable, which is what keeps the quiz engine self-scoring.
 */
enum QuestionType: string
{
    case Mcq = 'mcq';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
}
