<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EnrollmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $enrollments = Enrollment::query()
            ->where('user_id', $request->user()->getKey())
            ->with('course')
            ->latest()
            ->paginate(15);

        return EnrollmentResource::collection($enrollments);
    }

    public function store(Request $request, Course $course, EnrollmentService $service): JsonResponse
    {
        // Only published courses can be enrolled in.
        abort_unless($course->status === CourseStatus::Published, 404);

        $enrollment = $service->enroll($request->user(), $course);

        return (new EnrollmentResource($enrollment))
            ->response()
            ->setStatusCode($enrollment->wasRecentlyCreated ? 201 : 200);
    }
}
