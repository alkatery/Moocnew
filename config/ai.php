<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default assistant mode
    |--------------------------------------------------------------------------
    | Used before an admin has chosen one in the settings: off | rules | claude.
    */
    'default_mode' => env('AI_DEFAULT_MODE', 'off'),

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Claude) API
    |--------------------------------------------------------------------------
    | When `key` is empty the Claude engine falls back to a deterministic Fake
    | so the feature runs (and tests pass) without external calls — the same
    | pattern as the Fake payment gateway.
    */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 30),
    ],

    // Per-assistant model: a fast/cheap model tutors learners; a stronger one
    // analyses for staff.
    'models' => [
        'tutor' => env('AI_TUTOR_MODEL', 'claude-haiku-4-5-20251001'),
        'analyst' => env('AI_ANALYST_MODEL', 'claude-sonnet-4-6'),
    ],

    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
];
