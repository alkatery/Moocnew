<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Domain;

/**
 * A single turn in an assistant conversation.
 */
final readonly class ChatMessage
{
    public function __construct(
        public string $role,   // 'user' | 'assistant'
        public string $content,
    ) {}

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    /**
     * @return array{role: string, content: string}
     */
    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }
}
