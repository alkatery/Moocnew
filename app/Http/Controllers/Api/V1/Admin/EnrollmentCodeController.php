<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentCodeService;
use App\Contexts\Enrollment\Infrastructure\Persistence\EnrollmentCode;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEnrollmentCodeRequest;
use App\Http\Resources\EnrollmentCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Staff management of self-enrollment codes (courses.review): issue a code
 * for a course, list a course's codes with usage stats, and revoke a code.
 */
final class EnrollmentCodeController extends Controller
{
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(Permission::ReviewCourses->value), 403);

        $codes = EnrollmentCode::query()
            ->where('course_id', $course->getKey())
            ->latest()
            ->get();

        return EnrollmentCodeResource::collection($codes);
    }

    public function store(
        StoreEnrollmentCodeRequest $request,
        Course $course,
        EnrollmentCodeService $codes,
    ): JsonResponse {
        $code = $codes->issue(
            $request->user(),
            $course,
            $request->validated('max_uses') !== null ? (int) $request->validated('max_uses') : null,
            $request->validated('expires_at'),
        );

        return (new EnrollmentCodeResource($code))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, EnrollmentCode $code, ActivityLogger $activity): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ReviewCourses->value), 403);

        $code->delete();

        $activity->log('enrollment_code.deleted', $request->user(), $code, [
            'course_id' => $code->course_id,
        ]);

        return response()->json(status: 204);
    }
}
