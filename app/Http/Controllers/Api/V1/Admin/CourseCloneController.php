<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Catalog\Application\CourseCloner;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff deep-copy of a course (courses.review) — structure and assessments,
 * never learner data. The copy is created as a draft.
 */
final class CourseCloneController extends Controller
{
    public function __invoke(Request $request, Course $course, CourseCloner $cloner): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ReviewCourses->value), 403);

        $copy = $cloner->clone($request->user(), $course);

        return (new CourseResource($copy->load(['category', 'instructor'])))
            ->response()
            ->setStatusCode(201);
    }
}
