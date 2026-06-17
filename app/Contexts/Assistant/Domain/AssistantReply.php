<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Domain;

/**
 * The assistant's answer plus optional source labels (e.g. lesson titles the
 * answer drew on) and follow-up suggestions.
 */
final readonly class AssistantReply
{
    /**
     * @param  list<string>  $sources
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public string $text,
        public array $sources = [],
        public array $suggestions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'sources' => $this->sources,
            'suggestions' => $this->suggestions,
        ];
    }
}
