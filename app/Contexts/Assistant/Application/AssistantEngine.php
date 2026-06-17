<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Application;

use App\Contexts\Assistant\Domain\ChatMessage;

/**
 * A chat-completion engine. The `claude` assistant mode resolves this to the
 * real Anthropic engine when an API key is configured, otherwise to a
 * deterministic fake so the feature still runs offline and in tests.
 */
interface AssistantEngine
{
    /**
     * Produce the assistant's next message given a system prompt and the
     * conversation so far (oldest first).
     *
     * @param  list<ChatMessage>  $messages
     */
    public function reply(string $system, array $messages, string $model): string;
}
