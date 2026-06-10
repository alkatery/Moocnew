<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Catalog\Domain\Course\VideoStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Contexts\Shared\Application\FileUploader;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\LessonRequest;
use App\Http\Resources\LessonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LessonController extends Controller
{
    /**
     * Full authoring view of a lesson (content, transcript, asset…) for the
     * studio editor — owner/staff only.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($request->user()->can('update', $lesson->section->course), 403);

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'section_id' => $lesson->section_id,
                'title' => $lesson->title,
                'type' => $lesson->type->value,
                'content' => $lesson->content,
                'transcript' => $lesson->transcript,
                'asset_path' => $lesson->asset_path,
                'video_provider' => $lesson->video_provider?->value,
                'video_id' => $lesson->video_id,
                'video_status' => $lesson->video_status->value,
                'position' => $lesson->position,
                'is_free_preview' => $lesson->is_free_preview,
            ],
        ]);
    }

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
            'transcript',
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

    /**
     * Upload the lesson's asset (image lessons: jpg/png/webp; file lessons:
     * pdf and office documents). Replaces a previously uploaded asset.
     */
    public function uploadAsset(Request $request, Lesson $lesson, FileUploader $uploader): JsonResponse
    {
        abort_unless($request->user()->can('update', $lesson->section->course), 403);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:25600', // 25 MB
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,ppt,pptx,xls,xlsx,zip',
            ],
        ]);

        $url = $uploader->store($request->file('file'), 'lesson-assets', $lesson->asset_path);
        $lesson->update(['asset_path' => $url]);

        return response()->json(['data' => ['asset_path' => $url]]);
    }

    /**
     * Persist a new lesson order inside a section: `ids` is the full list of
     * the section's lesson ids in the desired display order.
     */
    public function reorder(Request $request, Section $section): JsonResponse
    {
        abort_unless($request->user()->can('update', $section->course), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $owned = $section->lessons()->pluck('id')->all();
        abort_unless(count($validated['ids']) === count($owned) && array_diff($validated['ids'], $owned) === [], 422);

        foreach ($validated['ids'] as $position => $id) {
            Lesson::query()->whereKey($id)->update(['position' => $position + 1]);
        }

        return response()->json(['message' => 'تم حفظ الترتيب.']);
    }
}
