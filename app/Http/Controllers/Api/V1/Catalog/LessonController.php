<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Domain\Course\VideoStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\LessonRequest;
use App\Http\Resources\LessonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LessonController extends Controller
{
    public function store(LessonRequest $request, Section $section): JsonResponse
    {
        $type = LessonType::from($request->validated('type'));

        $lesson = $section->lessons()->create([
            'title' => $request->validated('title'),
            'type' => $type,
            'content' => $request->validated('content'),
            'video_id' => $request->validated('video_id'),
            // A freshly attached video begins processing; everything else has no video.
            'video_status' => $type->requiresVideo() && $request->filled('video_id')
                ? VideoStatus::Processing
                : VideoStatus::None,
            'position' => $request->validated('position', 0),
            'is_free_preview' => (bool) $request->validated('is_free_preview', false),
        ]);

        return (new LessonResource($lesson))->response()->setStatusCode(201);
    }

    public function update(LessonRequest $request, Lesson $lesson): LessonResource
    {
        $lesson->update($request->safe()->only([
            'title',
            'type',
            'content',
            'video_id',
            'position',
            'is_free_preview',
        ]));

        return new LessonResource($lesson);
    }

    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($request->user()->can('update', $lesson->section->course), 403);

        $lesson->delete();

        return response()->json(status: 204);
    }
}
