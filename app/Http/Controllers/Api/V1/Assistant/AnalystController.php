<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Contexts\Assistant\Application\AdminAnalystService;
use App\Contexts\Assistant\Application\ConversationStore;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin analyst «بصيرة»: a staff chat that reports aggregate metrics and
 * recommends improvements. Requires analytics access; gated by assistant mode.
 */
final class AnalystController extends Controller
{
    public function chat(
        Request $request,
        AdminAnalystService $analyst,
        ConversationStore $store,
        ActivityLogger $activity,
    ): JsonResponse {
        abort_unless($request->user()->can(Permission::ViewAnalytics->value), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $conversation = $store->resolve($request->user(), 'admin', $data['conversation_id'] ?? null, null);
        $history = $store->history($conversation);

        $store->recordUser($conversation, $data['message']);
        $reply = $analyst->answer($data['message'], $history);
        $store->recordAssistant($conversation, $reply);

        $activity->log('assistant.admin_query', $request->user(), properties: ['message' => $data['message']]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'reply' => $reply->toArray(),
        ]);
    }
}
