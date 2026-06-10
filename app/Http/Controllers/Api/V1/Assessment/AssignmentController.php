<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AssignmentController extends Controller
{
    public function index(Request $request, Course $course, CourseAccess $access): AnonymousResourceCollection
    {
        abort_unless($access->canParticipate($request->user(), $course), 403);

        return AssignmentResource::collection(
            Assignment::query()->where('course_id', $course->getKey())->latest()->get(),
        );
    }

    public function store(StoreAssignmentRequest $request, Course $course): JsonResponse
    {
        $assignment = Assignment::query()->create([
            'course_id' => $course->getKey(),
            'section_id' => $request->validated('section_id'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'due_at' => $request->validated('due_at'),
            'points' => (int) $request->validated('points', 100),
            'rubric' => $request->validated('rubric'),
            'weight' => (int) $request->validated('weight', 1),
        ]);

        return (new AssignmentResource($assignment))->response()->setStatusCode(201);
    }
}
