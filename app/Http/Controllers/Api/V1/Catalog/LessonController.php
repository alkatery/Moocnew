<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Domain\Course\VideoProvider;
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
        $provider = $request->filled('video_provider')
            ? VideoProvider::from($request->validated('video_provider'))
            : null;

        $lesson = $section->lessons()->create([
            'title' => $request->validated('title'),
            'type' => $type,
            'content' => $request->validated('content'),
            'video_provider' => $provider,
            'video_id' => $request->validated('video_id'),
            // The initial status depends on the provider: managed (Bunny)
            // videos process asynchronously; storage/YouTube are ready at once.
            'video_status' => $type->requiresVideo() && $provider !== null
                ? $provider->initialStatus()
                : VideoStatus::None,
            'position' => $request->validated('position', 0),
            'is_free_preview' => (bool) $request->validated('is_free_preview', false),
        ]);

        return (new LessonResource($lesson))->response()->setStatusCode(201);
    }

    public function update(LessonRequest $request, Lesson $lesson): LessonResource
    {
        $lesson->fill($request->safe()->only([
            'title',
            'type',
            'content',
            'video_provider',
            'video_id',
            'position',
            'is_free_preview',
        ]));

        // Re-attaching a video resets its processing status to the provider's
        // initial state; clearing the video clears the status.
        if ($lesson->isDirty(['video_provider', 'video_id'])) {
            $lesson->video_status = $lesson->video_provider !== null && $lesson->video_id !== null
                ? $lesson->video_provider->initialStatus()
                : VideoStatus::None;
        }

        $lesson->save();

        return new LessonResource($lesson);
    }

    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($request->user()->can('update', $lesson->section->course), 403);

        $lesson->delete();

        return response()->json(status: 204);
    }
}
