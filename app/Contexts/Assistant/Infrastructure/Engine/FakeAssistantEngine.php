<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Infrastructure\Engine;

use App\Contexts\Assistant\Application\AssistantEngine;

/**
 * Deterministic stand-in for the Claude engine used when no API key is set
 * (and in tests). It grounds its reply in the supplied system prompt — which
 * already contains the retrieved course context or the metrics summary — so
 * the offline behaviour stays faithful to the real engine without any
 * external call. Mirrors the Fake payment gateway pattern.
 */
final class FakeAssistantEngine implements AssistantEngine
{
    public function reply(string $system, array $messages, string $model): string
    {
        $lastUser = '';
        foreach (array_reverse($messages) as $message) {
            if ($message->role === 'user') {
                $lastUser = $message->content;
                break;
            }
        }

        // Echo a grounded, helpful-sounding answer that demonstrably uses the
        // context block embedded in the system prompt.
        $context = $this->extractContext($system);

        $answer = "بناءً على محتوى المنصة، إليك ما يخص سؤالك: «{$lastUser}».";
        if ($context !== '') {
            $answer .= "\n\n".$context;
        }
        $answer .= "\n\n(وضع المعاينة دون مفتاح Claude — فعّل مفتاح الإنتاج للحصول على إجابات كاملة.)";

        return $answer;
    }

    private function extractContext(string $system): string
    {
        // The services delimit the grounding block with these markers.
        if (preg_match('/<context>(.*?)<\/context>/s', $system, $m) === 1) {
            return trim($m[1]);
        }

        return '';
    }
}
