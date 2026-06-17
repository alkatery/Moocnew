<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Domain;

/**
 * Which assistant engine the platform runs, chosen by the admin:
 * - Off    — assistants are disabled entirely.
 * - Rules  — deterministic answers from platform data, no LLM (zero cost).
 * - Claude — natural-language answers via Anthropic's Claude.
 */
enum AssistantMode: string
{
    case Off = 'off';
    case Rules = 'rules';
    case Claude = 'claude';

    public function isEnabled(): bool
    {
        return $this !== self::Off;
    }

    public function label(): string
    {
        return match ($this) {
            self::Off => 'معطّل',
            self::Rules => 'بدون ذكاء اصطناعي (قواعد)',
            self::Claude => 'ذكاء اصطناعي (Claude)',
        };
    }
}
