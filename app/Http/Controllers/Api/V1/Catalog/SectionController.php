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
}
