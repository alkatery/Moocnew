<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Application\LessonAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unified, access-gated delivery of a lesson's non-video content: the
 * article body, the image/file asset URL, and the transcript. Video
 * playback stays on the dedicated signed-playback endpoint; this endpoint
 * tells the player everything else it needs to render the lesson.
 */
final class LessonContentController extends Controller
{
    public function show(Request $request, Lesson $lesson, LessonAccess $access): JsonResponse
    {
        $lesson->loadMissing('section.course');

        abort_unless($access->canAccess($request->user(), $lesson), 403);

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type->value,
                'content' => in_array($lesson->type, [LessonType::Article, LessonType::Live], true) || $lesson->content !== null
                    ? $lesson->content
                    : null,
                'asset_path' => $lesson->type->usesAsset() ? $lesson->asset_path : null,
                'transcript' => $lesson->transcript,
            ],
        ]);
    }
}
