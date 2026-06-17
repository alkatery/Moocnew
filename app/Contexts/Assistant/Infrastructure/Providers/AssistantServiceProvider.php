<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Infrastructure\Providers;

use App\Contexts\Assistant\Application\AssistantEngine;
use App\Contexts\Assistant\Infrastructure\Engine\ClaudeEngine;
use App\Contexts\Assistant\Infrastructure\Engine\FakeAssistantEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Assistant context: binds the chat engine to the real Claude
 * implementation when an Anthropic API key is configured, otherwise to a
 * deterministic fake — so `claude` mode runs (and tests pass) without a key,
 * mirroring the Fake payment gateway.
 */
final class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AssistantEngine::class, function (): AssistantEngine {
            $key = config('ai.anthropic.key');

            return is_string($key) && $key !== ''
                ? new ClaudeEngine
                : new FakeAssistantEngine;
        });
    }
}
