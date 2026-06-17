<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Infrastructure\Engine;

use App\Contexts\Assistant\Application\AssistantEngine;
use App\Contexts\Assistant\Domain\ChatMessage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Claude chat-completion engine (Messages API). Used when
 * `ANTHROPIC_API_KEY` is configured and the assistant mode is `claude`.
 */
final class ClaudeEngine implements AssistantEngine
{
    public function reply(string $system, array $messages, string $model): string
    {
        $config = (array) config('ai.anthropic');

        $payload = [
            'model' => $model,
            'max_tokens' => (int) config('ai.max_tokens', 1024),
            'system' => $system,
            'messages' => array_map(
                static fn (ChatMessage $m): array => $m->toArray(),
                $messages,
            ),
        ];

        $response = Http::withHeaders([
            'x-api-key' => (string) ($config['key'] ?? ''),
            'anthropic-version' => (string) ($config['version'] ?? '2023-06-01'),
            'content-type' => 'application/json',
        ])
            ->timeout((int) ($config['timeout'] ?? 30))
            ->post(rtrim((string) ($config['base_url'] ?? ''), '/').'/messages', $payload);

        if ($response->failed()) {
            throw new RuntimeException('AI engine request failed: '.$response->status());
        }

        // The Messages API returns content as an array of blocks.
        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return trim($text) !== '' ? $text : 'تعذّر توليد إجابة الآن.';
    }
}
