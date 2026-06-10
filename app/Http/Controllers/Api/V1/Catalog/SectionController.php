<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SectionRequest;
use App\Http\Resources\SectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SectionController extends Controller
{
    public function store(SectionRequest $request, Course $course): JsonResponse
    {
        $section = $course->sections()->create([
            'title' => $request->validated('title'),
            'position' => $request->validated('position', 0),
        ]);

        return (new SectionResource($section))->response()->setStatusCode(201);
    }

    public function update(SectionRequest $request, Section $section): SectionResource
    {
        $section->update($request->safe()->only(['title', 'position']));

        return new SectionResource($section);
    }

    public function destroy(Request $request, Section $section): JsonResponse
    {
        abort_unless($request->user()->can('update', $section->course), 403);

        $section->delete();

        return response()->json(status: 204);
    }

    /**
     * Persist a new section order for a course: `ids` is the full list of
     * the course's section ids in the desired display order.
     */
    public function reorder(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('update', $course), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $owned = $course->sections()->pluck('id')->all();
        abort_unless(count($validated['ids']) === count($owned) && array_diff($validated['ids'], $owned) === [], 422);

        foreach ($validated['ids'] as $position => $id) {
            Section::query()->whereKey($id)->update(['position' => $position + 1]);
        }

        return response()->json(['message' => 'تم حفظ الترتيب.']);
    }
}
