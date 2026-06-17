<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Application;

use App\Contexts\Assistant\Domain\AssistantReply;
use App\Contexts\Assistant\Domain\ChatMessage;
use App\Contexts\Assistant\Infrastructure\Persistence\AssistantConversation;
use App\Contexts\Assistant\Infrastructure\Persistence\AssistantMessage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Persists assistant conversations and turns, and rebuilds the recent history
 * to feed back into the engine.
 */
final class ConversationStore
{
    public function resolve(User $user, string $scope, ?int $conversationId, ?int $courseId): AssistantConversation
    {
        if ($conversationId !== null) {
            return AssistantConversation::query()
                ->where('user_id', $user->getKey())
                ->where('scope', $scope)
                ->findOrFail($conversationId);
        }

        return AssistantConversation::query()->create([
            'user_id' => $user->getKey(),
            'scope' => $scope,
            'course_id' => $courseId,
        ]);
    }

    /**
     * The recent turns of a conversation, oldest first, as engine messages.
     *
     * @return list<ChatMessage>
     */
    public function history(AssistantConversation $conversation, int $limit = 10): array
    {
        return $conversation->messages()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (AssistantMessage $m): ChatMessage => new ChatMessage($m->role, $m->content))
            ->values()
            ->all();
    }

    public function recordUser(AssistantConversation $conversation, string $content): void
    {
        if ($conversation->title === null) {
            $conversation->update(['title' => Str::limit($content, 60)]);
        }

        $conversation->messages()->create(['role' => 'user', 'content' => $content]);
    }

    public function recordAssistant(AssistantConversation $conversation, AssistantReply $reply): AssistantMessage
    {
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply->text,
            'meta' => ['sources' => $reply->sources, 'suggestions' => $reply->suggestions],
        ]);
    }
}
