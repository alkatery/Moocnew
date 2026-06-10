<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Learning\Application\PathProgressService;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
use App\Contexts\Learning\Infrastructure\Persistence\PathEnrollment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The learner's side of learning paths: joining a path, enrolling in its
 * courses strictly in order, and listing their own paths with progress.
 */
final class PathEnrollmentController extends Controller
{
    public function __construct(
        private readonly PathProgressService $progress,
    ) {}

    public function mine(Request $request): JsonResponse
    {
        $memberships = PathEnrollment::query()
            ->where('user_id', $request->user()->getKey())
            ->with('path.items')
            ->latest()
            ->get();

        return response()->json([
            'data' => $memberships->map(fn (PathEnrollment $m): array => [
                'id' => $m->id,
                'status' => $m->status->value,
                'completed_at' => $m->completed_at?->toIso8601String(),
                'path' => [
                    'title' => $m->path->title,
                    'slug' => $m->path->slug,
                ],
                'progress' => $this->progress->progress($m->path, $request->user()),
            ]),
        ]);
    }

    public function enroll(Request $request, LearningPath $path): JsonResponse
    {
        abort_unless($path->isPublished(), 404);

        $membership = $this->progress->joinPath($request->user(), $path);

        return response()->json([
            'data' => [
                'id' => $membership->id,
                'status' => $membership->status->value,
            ],
        ], 201);
    }

    /**
     * Enroll in one of the path's courses — rejected (422) while earlier
     * items are incomplete, enforcing the path order server-side.
     */
    public function enrollCourse(Request $request, LearningPath $path, Course $course): JsonResponse
    {
        abort_unless($path->isPublished(), 404);

        $enrollment = $this->progress->enrollInCourse($request->user(), $path, $course);

        return response()->json([
            'data' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status->value,
                'course_id' => $enrollment->course_id,
            ],
        ], 201);
    }
}
