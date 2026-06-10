<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Contexts\Assistant\Application\ConversationStore;
use App\Contexts\Assistant\Application\StudentTutorService;
use App\Contexts\Assistant\Infrastructure\Persistence\AssistantConversation;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The learner tutor «مُعين»: a course-scoped chat that answers from the
 * course's content. Requires an active enrollment; gated by assistant mode.
 */
final class TutorController extends Controller
{
    public function chat(
        Request $request,
        Course $course,
        StudentTutorService $tutor,
        ConversationStore $store,
        CourseAccess $access,
    ): JsonResponse {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        abort_unless($access->hasActiveEnrollment($request->user(), $course->getKey()), 403);

        $conversation = $store->resolve($request->user(), 'student', $data['conversation_id'] ?? null, $course->getKey());
        $history = $store->history($conversation);

        $store->recordUser($conversation, $data['message']);
        $reply = $tutor->answer($course, $data['message'], $history);
        $store->recordAssistant($conversation, $reply);

        return response()->json([
            'conversation_id' => $conversation->id,
            'reply' => $reply->toArray(),
        ]);
    }

    public function history(Request $request, Course $course, ConversationStore $store): JsonResponse
    {
        $conversation = AssistantConversation::query()
            ->where('user_id', $request->user()->getKey())
            ->where('scope', 'student')
            ->where('course_id', $course->getKey())
            ->latest('id')
            ->first();

        if ($conversation === null) {
            return response()->json(['conversation_id' => null, 'messages' => []]);
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'messages' => $conversation->messages()->get(['role', 'content', 'meta']),
        ]);
    }
}
