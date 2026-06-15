<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Domain;

/**
 * Supported question types (PRD §5.هـ). All are objectively auto-gradable,
 * which is what keeps the quiz engine self-scoring.
 * E4 adds: dropdown, multi_select, numerical, regex.
 */
enum QuestionType: string
{
    case Mcq = 'mcq';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';

    // E4 — أنواع أسئلة إضافية
    case Dropdown = 'dropdown';
    case MultiSelect = 'multi_select';
    case Numerical = 'numerical';
    case Regex = 'regex';
}
