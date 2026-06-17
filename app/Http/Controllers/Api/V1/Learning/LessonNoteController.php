<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Application\LessonAccess;
use App\Contexts\Learning\Infrastructure\Persistence\LessonNote;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A learner's private, optionally time-anchored notes on a lesson —
 * the expert-practice "video notes" feature. Strictly owner-scoped.
 */
final class LessonNoteController extends Controller
{
    public function index(Request $request, Lesson $lesson, LessonAccess $access): JsonResponse
    {
        abort_unless($access->canAccess($request->user(), $lesson), 403);

        $notes = LessonNote::query()
            ->where('user_id', $request->user()->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->orderByRaw('at_seconds ASC NULLS LAST')
            ->orderBy('id')
            ->get(['id', 'at_seconds', 'body', 'created_at']);

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, Lesson $lesson, LessonAccess $access): JsonResponse
    {
        abort_unless($access->canAccess($request->user(), $lesson), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'at_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $note = LessonNote::query()->create([
            'user_id' => $request->user()->getKey(),
            'lesson_id' => $lesson->getKey(),
            'at_seconds' => $validated['at_seconds'] ?? null,
            'body' => $validated['body'],
        ]);

        return response()->json(['data' => $note->only(['id', 'at_seconds', 'body', 'created_at'])], 201);
    }

    public function destroy(Request $request, LessonNote $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->getKey(), 403);

        $note->delete();

        return response()->json(status: 204);
    }
}
